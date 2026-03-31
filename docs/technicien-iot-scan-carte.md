# Implémentation firmware ESP — Scan d'enregistrement de carte RFID

## Contexte

Le système permet d'enregistrer une nouvelle carte RFID sans connaître son UID à l'avance.
Voici le flux complet :

1. L'opérateur sélectionne un capteur dans l'interface web
2. L'interface envoie la commande **SCAN** (`0x100030`) au terminal via MQTT
3. Le terminal active un mode d'attente et signale visuellement à l'opérateur
4. L'opérateur présente la carte au capteur
5. Le terminal publie le UID avec un marqueur `"type":"scan"`
6. Le backend reçoit, broadcast le UID via WebSocket
7. L'interface reçoit le UID et remplit le champ automatiquement

---

## Topics MQTT concernés

| Direction | Topic | Description |
|-----------|-------|-------------|
| Backend → Terminal | `core/rfid/sensor/{serial_number}/response` | Commandes envoyées au terminal |
| Terminal → Backend | `core/rfid/sensor/{serial_number}/event` | Événements du terminal vers le backend |

`{serial_number}` = le numéro de série du terminal, ex: `RFID-2026-001`

---

## Commande à implémenter

### Code de la commande SCAN
```
0x100030
```

Le terminal la reçoit sur son topic `/response`, comme toutes les autres commandes existantes (RESET, REBOOT, etc.).

---

## Ce qu'il faut ajouter dans le firmware

### Étape 1 — Variables globales à ajouter

```cpp
bool scanMode = false;
unsigned long scanModeTimeout = 0;
const unsigned long SCAN_TIMEOUT_MS = 30000; // 30 secondes
```

---

### Étape 2 — Détecter la commande SCAN dans le callback MQTT

Dans la fonction qui gère les messages reçus sur le topic `/response` :

```cpp
void onMqttMessage(char* topic, byte* payload, unsigned int length) {
    String message = "";
    for (unsigned int i = 0; i < length; i++) {
        message += (char)payload[i];
    }
    message.trim();

    if (message == "0x100030") {
        // Commande SCAN : activer le mode enregistrement
        scanMode = true;
        scanModeTimeout = millis() + SCAN_TIMEOUT_MS;

        // Feedback visuel/sonore optionnel (LED bleue clignotante, bip court, etc.)
        // signalScanMode();

    } else if (message == "0x108070") {
        // RESET — comportement existant
    } else if (message == "0x108090") {
        // REBOOT — comportement existant
    }
    // ... autres commandes existantes inchangées
}
```

---

### Étape 3 — Modifier le comportement lors de la lecture d'une carte

Dans la fonction déclenchée quand une carte est présentée au lecteur :

```cpp
void onCardDetected(String uid) {
    uid.toUpperCase();

    String eventTopic = "core/rfid/sensor/" + String(SERIAL_NUMBER) + "/event";

    if (scanMode) {
        // Mode enregistrement : publier avec type="scan"
        // Le backend NE crée PAS de pointage, il renvoie juste le UID au frontend
        String payload = "{\"card_uid\":\"" + uid + "\",\"type\":\"scan\"}";
        mqttClient.publish(eventTopic.c_str(), payload.c_str());

        scanMode = false; // Désactiver immédiatement après la lecture

        // Feedback visuel/sonore optionnel (LED verte, double bip, etc.)
        // signalScanSuccess();

    } else {
        // Mode pointage normal — comportement INCHANGÉ
        String payload = "{\"card_uid\":\"" + uid + "\"}";
        mqttClient.publish(eventTopic.c_str(), payload.c_str());
    }
}
```

---

### Étape 4 — Gérer le timeout dans la boucle principale

Dans la fonction `loop()`, ajouter la vérification du timeout :

```cpp
void loop() {
    mqttClient.loop();

    // Annuler le mode scan si le timeout est dépassé (30s sans carte)
    if (scanMode && millis() > scanModeTimeout) {
        scanMode = false;
        // Feedback optionnel (LED rouge courte, bip d'erreur, etc.)
        // signalScanTimeout();
    }

    // ... reste de la boucle existante
}
```

---

## Format des payloads MQTT

### Scan d'enregistrement (NOUVEAU)
```json
{
  "card_uid": "A1B2C3D4",
  "type": "scan"
}
```

### Pointage normal (INCHANGÉ)
```json
{
  "card_uid": "A1B2C3D4"
}
```

La seule différence est la présence du champ `"type":"scan"`. Sans ce champ, le backend traite le message comme un pointage normal.

---

## Points importants

- **Ne pas modifier** le format des messages de pointage normal — le champ `type` est absent, le comportement reste identique
- En mode `scanMode`, **une seule carte est lue** puis le mode se désactive automatiquement
- Si aucune carte n'est présentée dans les **30 secondes**, le mode scan s'annule (côté terminal ET côté frontend simultanément via leurs propres timeouts)
- Le terminal **n'a pas besoin d'attendre une confirmation** du backend après un scan d'enregistrement
- La commande SCAN ne déclenche **aucun pointage**, aucun accès, aucune action physique (pas d'ouverture de porte, etc.)
