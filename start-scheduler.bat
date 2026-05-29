@echo off
REM Lance le planificateur Laravel en continu (dev Laragon).
REM Garde cette fenetre ouverte : elle execute schedule:run chaque minute,
REM ce qui declenche health:check-devices (bascule auto hors-ligne) et les autres taches.

cd /d "%~dp0"

REM Utilise le php de Laragon (adapter la version si besoin).
set PHP="C:\laragon\bin\php\php-8.4.12-nts-Win32-vs17-x64\php.exe"
if not exist %PHP% set PHP=php

echo Demarrage du planificateur Laravel (Ctrl+C pour arreter)...
%PHP% artisan schedule:work
