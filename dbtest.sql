PRAGMA foreign_keys=OFF;
BEGIN TRANSACTION;
CREATE TABLE plugins (
	id VARCHAR(30) NOT NULL,
	name VARCHAR NOT NULL,
	version VARCHAR NOT NULL,
	author VARCHAR NOT NULL,
	"Api_prefix" VARCHAR NOT NULL,
	tag_for_identified VARCHAR NOT NULL,
	"trigger" INTEGER NOT NULL,
	add_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	update_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	active BOOLEAN,
	PRIMARY KEY (id),
	UNIQUE (name),
	UNIQUE (version),
	UNIQUE (author),
	UNIQUE ("Api_prefix"),
	UNIQUE (tag_for_identified),
	UNIQUE ("trigger")
);
INSERT INTO plugins VALUES('26e6630e-3832-4d4e-9d58-18410e','Feelback','1.0.0','traore Eliezer','/app/feelback','[''Plugin'', ''feelback'']',1,'2025-07-02 10:11:57','2025-07-02 10:11:57',1);
CREATE TABLE admins (
	id VARCHAR NOT NULL,
	email VARCHAR NOT NULL,
	password VARCHAR NOT NULL,
	role VARCHAR(7),
	is_active BOOLEAN,
	otp_secret VARCHAR,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	update_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id)
);
CREATE TABLE users (
	id VARCHAR(30) NOT NULL,
	full_name VARCHAR NOT NULL,
	email VARCHAR NOT NULL,
	password VARCHAR NOT NULL,
	otp_secret VARCHAR,
	is_active BOOLEAN,
	created_at DATETIME,
	updated_at DATETIME,
	PRIMARY KEY (id)
);
INSERT INTO users VALUES('dec0cacc-4d94-42f6-b282-294235','ZOUNGRANA Nycaise Antime Jonathan','zoungrana110@gmail.com','$2b$12$ASSvUQn49.Vn3V0i01BjgOqdo5Ny8HxMrSYhZL/wMOCE7OTznlSdm','6D7AVIMXHIQ7KDLWKITOCCBANBMINWNR',1,'2025-07-02 11:26:10.223048','2025-07-02 11:27:07.453301');
INSERT INTO users VALUES('3055fe52-8a11-424a-8541-8e1758','YERBANGA Dieudonné Kévin','dieudonneyerbanga904@gmail.com','$2b$12$R0BSpKd4EAPeAmlR15FN0ekQbfI9GW.325lUe.sC906Jwf0FYasD2','EWKANQJTXODH6LGQN23BZZ33MW6RZ2HD',1,'2025-07-02 11:29:04.192540','2025-07-02 11:30:43.487125');
INSERT INTO users VALUES('8119edc3-a9ef-49c2-b646-4f851a','traore Eliezer B.','traoreera@gmail.com','$2b$12$a2rKfpQnUXpHvO2x3hkMoeX2SLh/5to7QQAuUATYQwZe/pi.DsSSe','W5XUVEOWCN3KULEJV72RMYPJDLDIN5RC',1,'2025-07-03 17:17:31.812531','2025-07-03 17:18:30.833458');
INSERT INTO users VALUES('04062e45-0361-4ab1-ba4c-4f2a25','Kamagate Mariam Sara','saramariamkamagate@gmail.com','$2b$12$Ng/9TodRARIO1G2fFgO6lelXnXG/CWlasBm29bZ378bsHgxXMnLaS','QL2XFVFHL6XKELSAC4ULLJGHL3YFXPLT',1,'2025-07-19 10:01:06.279326','2025-07-21 15:00:02.202254');
CREATE TABLE errors (
	id INTEGER NOT NULL,
	url VARCHAR(255),
	method VARCHAR(20),
	status_code INTEGER,
	message TEXT,
	traceback TEXT,
	created_at DATETIME,
	PRIMARY KEY (id)
);
CREATE TABLE admin_sessions (
	id VARCHAR NOT NULL,
	admin_id VARCHAR NOT NULL,
	session VARCHAR NOT NULL,
	create_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	expirate_at TIMESTAMP NOT NULL,
	PRIMARY KEY (id),
	FOREIGN KEY(admin_id) REFERENCES admins (id) ON DELETE CASCADE,
	UNIQUE (session)
);
CREATE TABLE IF NOT EXISTS "userSessions" (
	id VARCHAR(30) NOT NULL,
	user_id VARCHAR(30) NOT NULL,
	session VARCHAR NOT NULL,
	is_active BOOLEAN,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	expirate_at TIMESTAMP NOT NULL,
	PRIMARY KEY (id),
	FOREIGN KEY(user_id) REFERENCES users (id) ON DELETE CASCADE,
	UNIQUE (session)
);
INSERT INTO userSessions VALUES('9615c653-6a47-4d18-b8dd-eea300','dec0cacc-4d94-42f6-b282-294235','eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJ6b3VuZ3JhbmExMTBAZ21haWwuY29tIiwiZXhwIjoxNzUxNDU3NDMzfQ.L4Yvr3QEacJJfuZYcbghgWNGyUXzc0cUSnHSWylAUAM',1,'2025-07-02 11:27:13','2025-07-02 11:57:13.171824');
INSERT INTO userSessions VALUES('b5395c55-14b9-430f-81dd-655d60','3055fe52-8a11-424a-8541-8e1758','eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJkaWV1ZG9ubmV5ZXJiYW5nYTkwNEBnbWFpbC5jb20iLCJleHAiOjE3NTE0NTc2NDl9.KVA3FB3xOv023cz3runHPPY8KvNkRI36Zr7-pVvvwGQ',1,'2025-07-02 11:30:49','2025-07-02 12:00:49.262610');
INSERT INTO userSessions VALUES('32f61e66-1cd3-4d27-b315-7c8b0d','3055fe52-8a11-424a-8541-8e1758','eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJkaWV1ZG9ubmV5ZXJiYW5nYTkwNEBnbWFpbC5jb20iLCJleHAiOjE3NTE1Mzk3OTV9.G5hw33HGFy6fchZOujI_1X8EjVc-Hc_V2zIYi-N3xBs',1,'2025-07-03 10:19:55','2025-07-03 10:49:55.176409');
INSERT INTO userSessions VALUES('57b22449-3549-40f3-9498-9e272f','3055fe52-8a11-424a-8541-8e1758','eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJkaWV1ZG9ubmV5ZXJiYW5nYTkwNEBnbWFpbC5jb20iLCJleHAiOjE3NTE1NDI2MTJ9.WbECiQ8i-XQA4JgpGi0xCHCGBPgyJNBp2OoZxpsRTq8',1,'2025-07-03 11:06:52','2025-07-03 11:36:52.128133');
INSERT INTO userSessions VALUES('313f1650-73d1-439e-a5bd-28b28c','3055fe52-8a11-424a-8541-8e1758','eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJkaWV1ZG9ubmV5ZXJiYW5nYTkwNEBnbWFpbC5jb20iLCJleHAiOjE3NTE1NDU0OTd9.BUL88GeroACBiO_DzN1cf9KVCoPfNa5WJStYZh2SbNE',1,'2025-07-03 11:54:57','2025-07-03 12:24:57.720633');
INSERT INTO userSessions VALUES('181fc5a1-661a-4fd9-a7c7-552b6d','8119edc3-a9ef-49c2-b646-4f851a','eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJ0cmFvcmVlcmFAZ21haWwuY29tIiwiZXhwIjoxNzUxNTY0OTM5fQ.aeOAlR2zuq8F-a-FkRcCG2aWUM2gcMKeTA8a3VdZeuI',1,'2025-07-03 17:18:59','2025-07-03 17:48:59.218714');
INSERT INTO userSessions VALUES('5c07e56d-8e69-4268-89bb-8d26e5','8119edc3-a9ef-49c2-b646-4f851a','eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJ0cmFvcmVlcmFAZ21haWwuY29tIiwiZXhwIjoxNzUxNTY2NzM5fQ.8mzvAwoSH6EdlFFOKJUg9LqwuKApDO4HqHEDx8BszLw',1,'2025-07-03 17:48:59','2025-07-03 18:18:59.785618');
INSERT INTO userSessions VALUES('fd6ed8bb-bc49-48d7-8122-c7444e','dec0cacc-4d94-42f6-b282-294235','eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJ6b3VuZ3JhbmExMTBAZ21haWwuY29tIiwiZXhwIjoxNzUxNTY2ODIzfQ.2ndLpa-BzhUDVEePb51h74ljfDpV2wkc0l7lB3bM1aM',1,'2025-07-03 17:50:23','2025-07-03 18:20:23.093963');
INSERT INTO userSessions VALUES('21eb6b3d-6964-4059-b05c-abc0f0','8119edc3-a9ef-49c2-b646-4f851a','eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJ0cmFvcmVlcmFAZ21haWwuY29tIiwiZXhwIjoxNzUxNTY4NzE2fQ.19-sYcJ9-N5_J5F9DHL0nRwLiz_0kk-XuAZaK2V-2Go',1,'2025-07-03 18:21:56','2025-07-03 18:51:56.689531');
INSERT INTO userSessions VALUES('e617e0df-9bd9-4871-a640-5559af','8119edc3-a9ef-49c2-b646-4f851a','eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJ0cmFvcmVlcmFAZ21haWwuY29tIiwiZXhwIjoxNzUxNjMwNjE0fQ.hPnxxMpJdoVO_AJ_apgvPSB2jDhDG59sNojCCDb4MM4',1,'2025-07-04 11:33:34','2025-07-04 12:03:34.276131');
INSERT INTO userSessions VALUES('211378cc-9575-4d45-880f-80be2a','8119edc3-a9ef-49c2-b646-4f851a','eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJ0cmFvcmVlcmFAZ21haWwuY29tIiwiZXhwIjoxNzUxNjM5NzY0fQ.CfChf1sApTxyqbXdHG8ExpIyAXATKgAkn0WyTjvnV_M',1,'2025-07-04 14:06:04','2025-07-04 14:36:04.821551');
INSERT INTO userSessions VALUES('84bcb7dd-5b03-4be2-8520-2c45aa','dec0cacc-4d94-42f6-b282-294235','eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJ6b3VuZ3JhbmExMTBAZ21haWwuY29tIiwiZXhwIjoxNzUxNjUwNTU0fQ.y16NvRIFzIkIk1X2CvLT-akqJbMB2n19SFwo5T_lFhA',1,'2025-07-04 17:05:54','2025-07-04 17:35:54.238244');
INSERT INTO userSessions VALUES('9dacfb13-cf62-4a20-a502-8d82f7','8119edc3-a9ef-49c2-b646-4f851a','eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiJ0cmFvcmVlcmFAZ21haWwuY29tIiwiZXhwIjoxNzUxODMxODMyfQ.aCTH6FtwvyjZXryvMtiI_orOMijCnFYoGSIX98DqiNY',1,'2025-07-06 19:27:12','2025-07-06 19:57:12.168795');
CREATE TABLE feelbacks (
	id VARCHAR NOT NULL,
	user_id VARCHAR NOT NULL,
	topic VARCHAR NOT NULL,
	bad INTEGER NOT NULL,
	good INTEGER NOT NULL,
	middle INTEGER NOT NULL,
	localite TEXT,
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	CONSTRAINT uq_user_topic UNIQUE (user_id, topic)
);
INSERT INTO feelbacks VALUES('c5c9678e-6056-4d9b-9fb3-67bee6','04062e45-0361-4ab1-ba4c-4f2a25','Entrée 1',0,0,0,'Entrée principale ','2025-07-26 10:28:46','2025-07-26 10:28:46');
INSERT INTO feelbacks VALUES('79b39e54-64c5-4bb3-8865-7a2b22','6998da48-df0e-4b31-9da0-587349','Caisse 1 ',0,0,0,'','2025-08-05 17:35:05','2025-08-05 17:35:05');
INSERT INTO feelbacks VALUES('16683fba-ddef-42bb-9527-381a83','c7cc9906-b4f5-4c6a-bcdd-02bc85','Reception 4 roues',11,42,28,'Accueil clients','2025-08-07 18:07:02','2025-09-04 10:58:53');
INSERT INTO feelbacks VALUES('143b3fdf-6994-4415-87ec-c22384','c7cc9906-b4f5-4c6a-bcdd-02bc85','Service Express',5,8,5,'A l''entrée de l''atelier 4 roues','2025-08-08 11:49:35','2025-08-27 07:20:38');
INSERT INTO feelbacks VALUES('145c391e-d6cf-43a4-9955-343d98','dec0cacc-4d94-42f6-b282-294235','TANGAGROUP',3,8,5,'Bureau','2025-09-29 13:45:12','2025-11-05 16:35:41');
INSERT INTO feelbacks VALUES('4910bae5-abab-4c3e-a3bb-c9e0ab','3055fe52-8a11-424a-8541-8e1758','Caisse A',4,2,6,'Caisse A','2025-11-12 10:18:21','2025-12-30 13:19:01');
INSERT INTO feelbacks VALUES('119453df-de9c-40c4-a43d-09a92e','3055fe52-8a11-424a-8541-8e1758','Caisse B',1,2,2,'Caisse B','2025-11-12 10:21:07','2025-11-12 10:22:15');
INSERT INTO feelbacks VALUES('4d1cf920-c671-4400-b2bb-c17a29','0060aa46-fa89-480d-a78d-d717b7','Caisse 1',0,0,0,'Entrée principale','2026-02-06 15:43:40','2026-02-06 15:43:40');
INSERT INTO feelbacks VALUES('d00638ba-434a-40a2-94fd-72ef06','0060aa46-fa89-480d-a78d-d717b7','Caisse 2',0,0,0,'Entrée secondaire','2026-02-06 15:44:01','2026-02-06 15:44:01');
CREATE TABLE avis (
	id VARCHAR NOT NULL,
	feelback_id VARCHAR NOT NULL,
	user_id VARCHAR NOT NULL,
	identite VARCHAR NOT NULL,
	avis TEXT NOT NULL,
	PRIMARY KEY (id)
);
INSERT INTO avis VALUES('c3c0b2f7-faaf-4814-98e6-e1461e','9f6df7b8-1e48-4450-8c52-9371b9','3055fe52-8a11-424a-8541-8e1758','12345678','Service ');
INSERT INTO avis VALUES('2080e69b-229b-4a1b-9212-75489f','9f6df7b8-1e48-4450-8c52-9371b9','3055fe52-8a11-424a-8541-8e1758','123456789','Wtf');
INSERT INTO avis VALUES('f0e2d0a0-e2d5-4339-a53f-ec1cbb','9f6df7b8-1e48-4450-8c52-9371b9','3055fe52-8a11-424a-8541-8e1758','1223','Baby');
INSERT INTO avis VALUES('5f059e83-fbcd-4bb1-872e-adac89','9f6df7b8-1e48-4450-8c52-9371b9','3055fe52-8a11-424a-8541-8e1758','Ok','Test');
INSERT INTO avis VALUES('f19a3a8a-f232-4993-88d6-0b724a','9f6df7b8-1e48-4450-8c52-9371b9','3055fe52-8a11-424a-8541-8e1758','QK207676','Service très efficace');
INSERT INTO avis VALUES('6ef63f66-ae75-4107-93a0-424aa6','9f6df7b8-1e48-4450-8c52-9371b9','3055fe52-8a11-424a-8541-8e1758','Faut teste reste','En vrai cette bien fait ');
INSERT INTO avis VALUES('27fcf83c-3696-4536-a822-28c049','9f6df7b8-1e48-4450-8c52-9371b9','3055fe52-8a11-424a-8541-8e1758','Testing app','Votre avis compte ');
INSERT INTO avis VALUES('ec2bbe98-8add-4845-91cd-0efaae','9f6df7b8-1e48-4450-8c52-9371b9','3055fe52-8a11-424a-8541-8e1758','😅je teste pour m''en assurer ','His ');
INSERT INTO avis VALUES('609adecc-040b-4a7b-b8cd-32e497','9f6df7b8-1e48-4450-8c52-9371b9','3055fe52-8a11-424a-8541-8e1758','1223','Baby');
INSERT INTO avis VALUES('82dc10da-16b0-4428-9938-65f169','143b3fdf-6994-4415-87ec-c22384','c7cc9906-b4f5-4c6a-bcdd-02bc85','4564E303','Je n''ai pas reçu mon véhicule ');
INSERT INTO avis VALUES('4ef1d5bb-3124-4693-9a62-c8428b','9f6df7b8-1e48-4450-8c52-9371b9','3055fe52-8a11-424a-8541-8e1758','QK207676','Service très efficace');
CREATE TABLE tasks (
	id INTEGER NOT NULL,
	title VARCHAR NOT NULL,
	type VARCHAR,
	module VARCHAR NOT NULL,
	"moduleDir" VARCHAR,
	status BOOLEAN,
	description VARCHAR,
	version VARCHAR,
	author VARCHAR,
	"metaFile" JSON,
	PRIMARY KEY (id),
	UNIQUE (module)
);
INSERT INTO tasks VALUES(1,'feelback mqtt','service','feedback','plugins/feelback',1,replace('\n        This service listens to MQTT messages related to\n        feelback operations and processes them accordingly.\n        The service will listen to MQTT messages related\n        to feelback operations and processes them accordingly.\n        It will listen to MQTT messages related to feelback\n        operations and processes them accordingly.\n    ','\n',char(10)),'1.0.0','Tanga Group','"{\"title\": \"feelback mqtt\", \"description\": \"\\n        This service listens to MQTT messages related to\\n        feelback operations and processes them accordingly.\\n        The service will listen to MQTT messages related\\n        to feelback operations and processes them accordingly.\\n        It will listen to MQTT messages related to feelback\\n        operations and processes them accordingly.\\n    \", \"version\": \"1.0.0\", \"author\": \"Tanga Group\", \"type\": \"service\", \"module\": \"plugins\", \"moduleDir\": \"plugins/feelback\", \"status\": true, \"dependencies\": [\"mqtt\"], \"license\": \"MIT\", \"tags\": [\"mqtt\", \"service\", \"plugins\"], \"icon\": \"mdi:message-text\", \"homepage\": \"app/feelback\", \"documentation\": \"http://app.tangagroup.com/docs/feelback\", \"repository\": \"http://app.tangagroup.com/repo/feelback\", \"issues\": \"http://app.tangagroup.com/issues/feelback\", \"changelog\": \"http://app.tangagroup.com/changelog/feelback\", \"support\": \"http://app.tangagroup.com/support/feelback\", \"contact\": {\"email\": \"contact@tangagroup.com\", \"website\": \"http://app.tangagroup.com\", \"phone\": \"+1234567890\"}, \"keywords\": [\"mqtt\", \"feelback\", \"service\", \"plugins\"], \"created_at\": \"2023-10-01T00:00:00Z\", \"updated_at\": \"2023-10-01T00:00:00Z\", \"license_url\": \"http://app.tangagroup.com/license\"}"');
INSERT INTO tasks VALUES(2,'presence mqtt','service','presence','plugins/feelback',1,replace('\n        This service listens to MQTT messages related to\n        presense operations and processes them accordingly.\n        The service will listen to MQTT messages related\n        to feelback operations and processes them accordingly.\n        It will listen to MQTT messages related to feelback\n        operations and processes them accordingly.\n    ','\n',char(10)),'1.0.0','Tanga Group','"{\"title\": \"presence mqtt\", \"description\": \"\\n        This service listens to MQTT messages related to\\n        presense operations and processes them accordingly.\\n        The service will listen to MQTT messages related\\n        to feelback operations and processes them accordingly.\\n        It will listen to MQTT messages related to feelback\\n        operations and processes them accordingly.\\n    \", \"version\": \"1.0.0\", \"author\": \"Tanga Group\", \"type\": \"service\", \"module\": \"plugins\", \"moduleDir\": \"plugins/feelback\", \"status\": true, \"dependencies\": [\"mqtt\"], \"license\": \"MIT\", \"tags\": [\"mqtt\", \"service\", \"plugins\"], \"icon\": \"mdi:message-text\", \"homepage\": \"app/feelback\", \"documentation\": \"https://app.tangagroup.com/docs/presence\", \"repository\": \"https://app.tangagroup.com/repo/presence\", \"issues\": \"https://app.tangagroup.com/issues/presence\", \"changelog\": \"https://app.tangagroup.com/changelog/presence\", \"support\": \"https://app.tangagroup.com/support/presence\", \"contact\": {\"email\": \"contact@tangagroup.com\", \"website\": \"https://app.tangagroup.com\", \"phone\": \"+1234567890\"}, \"keywords\": [\"mqtt\", \"feelback\", \"service\", \"plugins\"], \"created_at\": \"2023-10-01T00:00:00Z\", \"updated_at\": \"2023-10-01T00:00:00Z\", \"license_url\": \"https://app.tangagroup.com/license\"}"');
INSERT INTO tasks VALUES(3,'presence mqtt','service','finger','plugins/feelback',1,replace('\n        This service listens to MQTT messages related to\n        presense operations and processes them accordingly.\n        The service will listen to MQTT messages related\n        to feelback operations and processes them accordingly.\n        It will listen to MQTT messages related to feelback\n        operations and processes them accordingly.\n    ','\n',char(10)),'1.0.0','Tanga Group','"{\"title\": \"presence mqtt\", \"description\": \"\\n        This service listens to MQTT messages related to\\n        presense operations and processes them accordingly.\\n        The service will listen to MQTT messages related\\n        to feelback operations and processes them accordingly.\\n        It will listen to MQTT messages related to feelback\\n        operations and processes them accordingly.\\n    \", \"version\": \"1.0.0\", \"author\": \"Tanga Group\", \"type\": \"service\", \"module\": \"plugins\", \"moduleDir\": \"plugins/feelback\", \"status\": true, \"dependencies\": [\"mqtt\"], \"license\": \"MIT\", \"tags\": [\"mqtt\", \"service\", \"plugins\"], \"icon\": \"mdi:message-text\", \"homepage\": \"app/feelback\", \"documentation\": \"https://app.tangagroup.com/docs/presence\", \"repository\": \"https://app.tangagroup.com/repo/presence\", \"issues\": \"https://app.tangagroup.com/issues/presence\", \"changelog\": \"https://app.tangagroup.com/changelog/presence\", \"support\": \"https://app.tangagroup.com/support/presence\", \"contact\": {\"email\": \"contact@tangagroup.com\", \"website\": \"https://app.tangagroup.com\", \"phone\": \"+1234567890\"}, \"keywords\": [\"mqtt\", \"feelback\", \"service\", \"plugins\"], \"created_at\": \"2023-10-01T00:00:00Z\", \"updated_at\": \"2023-10-01T00:00:00Z\", \"license_url\": \"https://app.tangagroup.com/license\"}"');
CREATE TABLE IF NOT EXISTS "Presense" (
	id VARCHAR NOT NULL,
	user_id VARCHAR NOT NULL,
	topic VARCHAR NOT NULL,
	label VARCHAR,
	actif BOOLEAN,
	"niveauBatterie" INTEGER,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	last_update DATETIME DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	CONSTRAINT uq_user_label UNIQUE (user_id, label),
	FOREIGN KEY(user_id) REFERENCES users (id)
);
INSERT INTO Presense VALUES('fbdb662a-921d-443d-a7ef-ce3c9f','3055fe52-8a11-424a-8541-8e1758','ISGE','Administration Professeur',0,100,'2025-09-23 08:22:25','2025-09-23 08:22:25');
INSERT INTO Presense VALUES('158af829-fb17-47cc-92ff-5b2421','dec0cacc-4d94-42f6-b282-294235','TANGAGROUP','Bureau',0,100,'2025-09-29 13:46:24','2025-09-29 13:46:24');
INSERT INTO Presense VALUES('b3e7ece6-a7d8-4c4d-8275-b0bdb7','fd03e4a2-52a0-4951-b955-223c32','Bureau','Administration',0,100,'2025-10-27 13:25:08','2025-10-27 13:25:08');
INSERT INTO Presense VALUES('6acdfee8-8c6c-4297-8433-49953d','9ebf64a6-232b-4c67-bc4a-a2be33','Kawtalmedia','Kawtal media',0,100,'2025-10-28 08:50:16','2025-10-28 08:50:16');
INSERT INTO Presense VALUES('32885df5-7b3c-4e9c-b2b6-71ddf2','543d5b70-ad6a-479b-b3f1-5e1cf9','GoldenSport','Golden Sport',0,100,'2025-12-18 10:31:35','2025-12-18 10:31:35');
INSERT INTO Presense VALUES('e7934fef-faaf-414e-8cb9-ec5771','bf0b4cf0-3da5-451c-9fff-9c7a58','Rissebelle','😍Rissebelle',0,100,'2026-01-13 09:53:13','2026-01-13 09:53:13');
INSERT INTO Presense VALUES('e8dc2b4e-9aa5-4419-be59-fae5d5','f944ef99-ec18-46fc-b37b-4444b9','leabassono','leabassono',0,100,'2026-01-21 12:45:38','2026-01-21 12:45:38');
INSERT INTO Presense VALUES('67326ea0-d97f-4aeb-96df-5bddb4','608f4ebe-8c3a-4ea4-8977-0f9766','angedusol','angedusol',0,100,'2026-01-21 13:29:20','2026-01-21 13:29:20');
INSERT INTO Presense VALUES('7da2812d-a465-417d-8af7-c5a37d','0060aa46-fa89-480d-a78d-d717b7','fdht','hfjh',0,100,'2026-02-06 15:30:06','2026-02-06 15:30:06');
INSERT INTO Presense VALUES('8d8bc0ed-4676-4641-85cb-2a93c6','0060aa46-fa89-480d-a78d-d717b7','zfe','zt''r',0,100,'2026-02-06 15:31:45','2026-02-06 15:31:45');
INSERT INTO Presense VALUES('9bc601dd-5d8a-425a-99c2-e2af48','0060aa46-fa89-480d-a78d-d717b7','dfggh','a',0,100,'2026-02-06 15:33:38','2026-02-06 15:33:38');
INSERT INTO Presense VALUES('bc645250-cfc9-4630-8371-d3b1f7','5f9c1035-2ea4-4db1-9fe9-fe4137','LORAGE/deco','Usine',0,100,'2026-02-13 08:18:40','2026-02-13 08:18:40');
INSERT INTO Presense VALUES('63066763-67f2-4535-ae7b-a31562','5f9c1035-2ea4-4db1-9fe9-fe4137','LORAGE/deco','Bureau',0,100,'2026-02-13 08:19:02','2026-02-13 08:19:02');
CREATE TABLE IF NOT EXISTS "PresenseEvents" (
	id VARCHAR NOT NULL,
	uid VARCHAR NOT NULL,
	user_id VARCHAR,
	topic VARCHAR,
	event_type VARCHAR(8) NOT NULL,
	message TEXT,
	event_date DATETIME DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	FOREIGN KEY(user_id) REFERENCES users (id)
);
INSERT INTO PresenseEvents VALUES('4e815064-9448-47b5-a1da-6e52e0','44:B8:F8:85','dec0cacc-4d94-42f6-b282-294235','YERBANGA Kevin','ADD',NULL,'2025-09-29 14:23:09');
INSERT INTO PresenseEvents VALUES('db1bcab8-37b9-400d-9a8f-a6f0dd','44:B8:F8:85','dec0cacc-4d94-42f6-b282-294235','YERBANGA Kevin','GRANTED','user dec0cacc-4d94-42f6-b282-294235 as CardEventType.GRANTED','2025-09-29 14:23:58');
INSERT INTO PresenseEvents VALUES('36aed570-ccb8-473c-af4a-9aba6e','44:B8:F8:85','dec0cacc-4d94-42f6-b282-294235','YERBANGA Kevin','GRANTED','user dec0cacc-4d94-42f6-b282-294235 as CardEventType.GRANTED','2025-09-29 14:23:58');
INSERT INTO PresenseEvents VALUES('a600af96-8b90-4ab8-ab49-57b516','44:B8:F8:85','dec0cacc-4d94-42f6-b282-294235','YERBANGA Kevin','GRANTED','user dec0cacc-4d94-42f6-b282-294235 as CardEventType.GRANTED','2025-09-29 14:24:23');
INSERT INTO PresenseEvents VALUES('4d77cd39-29b1-4ff3-b945-6ff4bd','44:B8:F8:85','dec0cacc-4d94-42f6-b282-294235','YERBANGA Kevin','GRANTED','user dec0cacc-4d94-42f6-b282-294235 as CardEventType.GRANTED','2025-09-29 14:24:23');
INSERT INTO PresenseEvents VALUES('ff97fa7d-156f-4a36-85be-d00834','44:B8:F8:85','dec0cacc-4d94-42f6-b282-294235','YERBANGA Kevin','GRANTED','user dec0cacc-4d94-42f6-b282-294235 as CardEventType.GRANTED','2025-09-29 14:24:58');
INSERT INTO PresenseEvents VALUES('4dbcf4c8-2baf-4a84-9228-8eabe1','44:B8:F8:85','dec0cacc-4d94-42f6-b282-294235','YERBANGA Kevin','GRANTED','user dec0cacc-4d94-42f6-b282-294235 as CardEventType.GRANTED','2025-09-29 14:24:58');
INSERT INTO PresenseEvents VALUES('c888efcc-9f99-46be-90b8-42630d','44:B8:F8:85','dec0cacc-4d94-42f6-b282-294235','YERBANGA Kevin','GRANTED','user dec0cacc-4d94-42f6-b282-294235 as CardEventType.GRANTED','2025-09-29 14:25:18');
CREATE TABLE IF NOT EXISTS "PresenceCartes" (
	id VARCHAR NOT NULL,
	user_id VARCHAR NOT NULL,
	locket_id VARCHAR NOT NULL,
	uid VARCHAR NOT NULL,
	label VARCHAR,
	types VARCHAR(10) NOT NULL,
	status BOOLEAN,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	CONSTRAINT uq_user_uid UNIQUE (user_id, uid),
	FOREIGN KEY(user_id) REFERENCES users (id),
	FOREIGN KEY(locket_id) REFERENCES "Presense" (id)
);
INSERT INTO PresenceCartes VALUES('4700a547-7b22-414c-bebe-be4705','3055fe52-8a11-424a-8541-8e1758','fbdb662a-921d-443d-a7ef-ce3c9f','44:B8:F8:85','Yerbanga Kevin','SIMPLECARD',1,'2025-09-23 08:23:33');
INSERT INTO PresenceCartes VALUES('140bf4c8-9d7f-4a14-94ae-832fef','dec0cacc-4d94-42f6-b282-294235','158af829-fb17-47cc-92ff-5b2421','44:B8:F8:85','YERBANGA Kevin','SIMPLECARD',1,'2025-09-29 14:23:09');
INSERT INTO PresenceCartes VALUES('b389a032-0820-4bd2-8c4d-656a5a','dec0cacc-4d94-42f6-b282-294235','158af829-fb17-47cc-92ff-5b2421','E3:13:6C:FE','TRAORE','SIMPLECARD',1,'2025-10-11 09:13:53');
INSERT INTO PresenceCartes VALUES('310fe62b-bc2b-404c-b1ca-a01e7c','dec0cacc-4d94-42f6-b282-294235','158af829-fb17-47cc-92ff-5b2421','B3:B8:AD:13','Kalifa','SIMPLECARD',1,'2025-10-11 09:14:20');
INSERT INTO PresenceCartes VALUES('dd55d494-1192-4e85-a8c7-c2ae82','dec0cacc-4d94-42f6-b282-294235','158af829-fb17-47cc-92ff-5b2421','B3:11:FA:2E','Sandra','SIMPLECARD',1,'2025-10-11 09:15:04');
INSERT INTO PresenceCartes VALUES('bc57b131-5cf4-4953-9cde-e28bb3','fd03e4a2-52a0-4951-b955-223c32','b3e7ece6-a7d8-4c4d-8275-b0bdb7','17:71:02:04','RABDO YACOUBA','SIMPLECARD',1,'2025-10-27 13:26:39');
CREATE TABLE IF NOT EXISTS "Finger" (
	id VARCHAR NOT NULL,
	user_id VARCHAR NOT NULL,
	topic VARCHAR NOT NULL,
	label VARCHAR,
	actif BOOLEAN,
	"niveauBatterie" INTEGER,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	last_update DATETIME DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	CONSTRAINT uq_user_label UNIQUE (user_id, label),
	FOREIGN KEY(user_id) REFERENCES users (id)
);
INSERT INTO Finger VALUES('3ef7e445-12ae-4817-a366-a0c465','552254e7-fd3c-4288-9320-57dc77','rassidi/finger','RASSIDIMARKET',0,100,'2026-01-29 19:04:18','2026-01-29 19:04:18');
INSERT INTO Finger VALUES('74ae0ab8-cc18-401a-a85f-29f31f','0060aa46-fa89-480d-a78d-d717b7','empreinte','boîtier 1',0,100,'2026-02-06 15:51:47','2026-02-06 15:51:47');
CREATE TABLE IF NOT EXISTS "FinterIdentification" (
	id VARCHAR NOT NULL,
	user_id VARCHAR NOT NULL,
	locket_id VARCHAR NOT NULL,
	uid VARCHAR NOT NULL,
	label VARCHAR,
	types VARCHAR(10) NOT NULL,
	status BOOLEAN,
	created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	CONSTRAINT uq_user_uid UNIQUE (user_id, uid),
	FOREIGN KEY(user_id) REFERENCES users (id),
	FOREIGN KEY(locket_id) REFERENCES "Presense" (id)
);
INSERT INTO FinterIdentification VALUES('ea248851-3967-4461-b867-602750','552254e7-fd3c-4288-9320-57dc77','3ef7e445-12ae-4817-a366-a0c465','1','TASSEMBEDO DENISE','SIMPLECARD',1,'2026-01-29 19:05:08');
INSERT INTO FinterIdentification VALUES('f91a24dd-2849-4927-ad20-614fe4','552254e7-fd3c-4288-9320-57dc77','3ef7e445-12ae-4817-a366-a0c465','2','SIMPORE NADEGE','SIMPLECARD',1,'2026-01-29 19:05:29');
INSERT INTO FinterIdentification VALUES('664aa246-3a98-4785-921e-4d0c1c','552254e7-fd3c-4288-9320-57dc77','3ef7e445-12ae-4817-a366-a0c465','3','GUIATIN ULRICH','SIMPLECARD',1,'2026-01-29 19:13:32');
INSERT INTO FinterIdentification VALUES('cb90b3c2-469a-4957-a428-44751f','552254e7-fd3c-4288-9320-57dc77','3ef7e445-12ae-4817-a366-a0c465','4','PARE LATIFA','SIMPLECARD',1,'2026-01-29 19:13:47');
INSERT INTO FinterIdentification VALUES('e6e7f2ae-5ffc-4d8e-a972-a4db97','552254e7-fd3c-4288-9320-57dc77','3ef7e445-12ae-4817-a366-a0c465','5','OUEDRAOGO AWA','SIMPLECARD',1,'2026-01-29 19:14:06');
INSERT INTO FinterIdentification VALUES('aa6301f7-e484-4a43-961b-e09001','552254e7-fd3c-4288-9320-57dc77','3ef7e445-12ae-4817-a366-a0c465','6','BELEM ZALISSA','SIMPLECARD',1,'2026-01-29 19:14:25');
CREATE TABLE IF NOT EXISTS "FingerEvents" (
	id VARCHAR NOT NULL,
	uid VARCHAR NOT NULL,
	user_id VARCHAR,
	topic VARCHAR,
	event_type VARCHAR(8) NOT NULL,
	message TEXT,
	event_date DATETIME DEFAULT CURRENT_TIMESTAMP,
	PRIMARY KEY (id),
	FOREIGN KEY(user_id) REFERENCES users (id)
);
INSERT INTO FingerEvents VALUES('709b58c6-43a0-40f7-abc3-85b4ed','ESP connected','608f4ebe-8c3a-4ea4-8977-0f9766','Uknow card','DENIED','user 608f4ebe-8c3a-4ea4-8977-0f9766 as CardEventType.DENIED','2026-01-29 16:15:48');
INSERT INTO FingerEvents VALUES('e9d95f9b-3e8a-4b51-8242-dc062c','2','608f4ebe-8c3a-4ea4-8977-0f9766','EMPLOYE 5','GRANTED','user 608f4ebe-8c3a-4ea4-8977-0f9766 as CardEventType.GRANTED','2026-01-29 16:16:12');
INSERT INTO FingerEvents VALUES('f5c22f4e-dd5d-4b2a-b6ce-4cfe91','ESP connected','608f4ebe-8c3a-4ea4-8977-0f9766','Uknow card','DENIED','user 608f4ebe-8c3a-4ea4-8977-0f9766 as CardEventType.DENIED','2026-01-29 16:17:07');
INSERT INTO FingerEvents VALUES('f96f1305-0c9d-4d6e-b2a0-725809','2','608f4ebe-8c3a-4ea4-8977-0f9766','EMPLOYE 5','GRANTED','user 608f4ebe-8c3a-4ea4-8977-0f9766 as CardEventType.GRANTED','2026-01-29 16:17:18');
INSERT INTO FingerEvents VALUES('9eac4ed7-ca31-4050-b2d3-b8c46b','ESP connected','552254e7-fd3c-4288-9320-57dc77','Uknow card','DENIED','user 552254e7-fd3c-4288-9320-57dc77 as CardEventType.DENIED','2026-02-01 15:20:45');
INSERT INTO FingerEvents VALUES('93261e6a-f77f-4769-928f-ffc31a','ESP connected','552254e7-fd3c-4288-9320-57dc77','Uknow card','DENIED','user 552254e7-fd3c-4288-9320-57dc77 as CardEventType.DENIED','2026-02-01 15:30:47');
INSERT INTO FingerEvents VALUES('802dc1fe-75a7-453c-b5ed-e2aef0','ESP connected','552254e7-fd3c-4288-9320-57dc77','Uknow card','DENIED','user 552254e7-fd3c-4288-9320-57dc77 as CardEventType.DENIED','2026-02-01 15:40:51');
INSERT INTO FingerEvents VALUES('80886e9f-2fac-4ffc-bca9-0e8359','ESP connected','552254e7-fd3c-4288-9320-57dc77','Uknow card','DENIED','user 552254e7-fd3c-4288-9320-57dc77 as CardEventType.DENIED','2026-02-01 15:50:53');
INSERT INTO FingerEvents VALUES('75824a95-c283-4052-8ddc-2736e3','ESP connected','552254e7-fd3c-4288-9320-57dc77','Uknow card','DENIED','user 552254e7-fd3c-4288-9320-57dc77 as CardEventType.DENIED','2026-02-01 16:01:11');
INSERT INTO FingerEvents VALUES('74ce85fd-29d1-45e1-9a9f-60c0f2','ESP connected','552254e7-fd3c-4288-9320-57dc77','Uknow card','DENIED','user 552254e7-fd3c-4288-9320-57dc77 as CardEventType.DENIED','2026-02-01 16:11:03');
INSERT INTO FingerEvents VALUES('d470e70d-8716-4cf0-986d-1c9de6','ESP connected','552254e7-fd3c-4288-9320-57dc77','Uknow card','DENIED','user 552254e7-fd3c-4288-9320-57dc77 as CardEventType.DENIED','2026-02-01 16:21:07');
INSERT INTO FingerEvents VALUES('0bb54605-85b7-47eb-9e69-4d89fd','ESP connected','552254e7-fd3c-4288-9320-57dc77','Uknow card','DENIED','user 552254e7-fd3c-4288-9320-57dc77 as CardEventType.DENIED','2026-02-01 16:31:13');
INSERT INTO FingerEvents VALUES('6f0cd096-c218-4328-9984-bcc82d','ESP connected','552254e7-fd3c-4288-9320-57dc77','Uknow card','DENIED','user 552254e7-fd3c-4288-9320-57dc77 as CardEventType.DENIED','2026-02-01 17:41:30');
CREATE UNIQUE INDEX ix_plugins_id ON plugins (id);
CREATE UNIQUE INDEX ix_admins_email ON admins (email);
CREATE UNIQUE INDEX ix_admins_id ON admins (id);
CREATE INDEX ix_users_id ON users (id);
CREATE UNIQUE INDEX ix_users_email ON users (email);
CREATE UNIQUE INDEX ix_admin_sessions_id ON admin_sessions (id);
CREATE UNIQUE INDEX "ix_userSessions_id" ON "userSessions" (id);
CREATE INDEX ix_feelbacks_id ON feelbacks (id);
CREATE INDEX ix_feelbacks_user_id ON feelbacks (user_id);
CREATE INDEX ix_avis_feelback_id ON avis (feelback_id);
CREATE INDEX ix_avis_user_id ON avis (user_id);
CREATE UNIQUE INDEX ix_avis_id ON avis (id);
CREATE INDEX ix_tasks_id ON tasks (id);
CREATE INDEX "ix_Presense_user_id" ON "Presense" (user_id);
CREATE INDEX "ix_PresenceCartes_locket_id" ON "PresenceCartes" (locket_id);
CREATE INDEX "ix_PresenceCartes_user_id" ON "PresenceCartes" (user_id);
CREATE INDEX "ix_Finger_user_id" ON "Finger" (user_id);
CREATE INDEX "ix_FinterIdentification_user_id" ON "FinterIdentification" (user_id);
CREATE INDEX "ix_FinterIdentification_locket_id" ON "FinterIdentification" (locket_id);
COMMIT;
