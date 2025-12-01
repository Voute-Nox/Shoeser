# 👞 Shoeser - Écosystème de Gestion de Stock

![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4?style=for-the-badge&logo=php&logoColor=white)
![Java](https://img.shields.io/badge/Java-17%2B-ED8B00?style=for-the-badge&logo=openjdk&logoColor=white)
![Python](https://img.shields.io/badge/Python-3.x-3776AB?style=for-the-badge&logo=python&logoColor=white)
![PostgreSQL](https://img.shields.io/badge/PostgreSQL-316192?style=for-the-badge&logo=postgresql&logoColor=white)

**Shoeser** est une solution complète pour la gestion d'une chaîne de magasins de chaussures. Elle combine :
1.  Une **Interface Web (Intranet)** pour la gestion RH et la supervision globale.
2.  Une **Architecture Client/Serveur (Socket)** pour les terminaux de stock en magasin (scan code-barres, inventaire rapide).

Les deux systèmes partagent la même base de données PostgreSQL en temps réel.

---

## 📑 Sommaire

- [Fonctionnalités](#-fonctionnalités)
- [Architecture Technique](#-architecture-technique)
- [Installation et Configuration](#-installation-et-configuration)
- [Protocole Socket](#-protocole-socket-terminal)
- [Arborescence du Projet](#-arborescence-du-projet)
- [Auteurs](#-auteurs)

---

## 🚀 Fonctionnalités

### 🌐 Module Web (Intranet)
* **Rôles Hiérarchiques** : Gérant (Admin), Manager, Employé.
* **Supervision** : Vue globale des stocks et alertes de rupture.
* **RH** : Gestion du personnel, blocage de compte, traçabilité des actions.
* **Catalogue** : Filtres dynamiques (JS), mode sombre/clair, fiches produits détaillées.

### 📟 Module Terminal (Socket Java/Python)
Ce module simule un terminal de stock utilisé en rayon.
* **Serveur Centralisé (Java)** :
    * **Architecture Mono-Client** : Un système de "Videur" rejette les connexions si le serveur est déjà occupé (Sécurité de concurrence).
    * **Connexion Directe BDD** : Intéraction JDBC avec PostgreSQL.
* **Client Terminal (Python)** :
    * **Scan & Recherche** : Recherche de produit par Code-Barres ou ID.
    * **Mise à jour Stock** : Modification instantanée des quantités en rayon.
    * **Interface Asynchrone** : Thread d'écoute pour recevoir les réponses du serveur sans bloquer la saisie utilisateur.

---

## 🛠 Architecture Technique

Le projet repose sur une architecture hybride :

1.  **Base de Données** : PostgreSQL (Hébergé sur AlwaysData). Point de vérité unique.
2.  **Backend Web** : PHP natif (PDO).
3.  **Backend Socket** : Java (ServerSocket). Écoute par défaut sur le port `50000`.
4.  **Frontend Terminal** : Python (Sockets TCP/IP).

---

## ⚙️ Installation et Configuration

### Pré-requis
* **Web** : CSV des clefs d'inscription `key.csv` pour l'inscription d'un utilisateur.
* **Socket Serveur** : Java JDK 11+ et le driver JDBC PostgreSQL (`postgresql-42.x.jar`).
* **Socket Client** : Python 3.x.

### 1. Configuration de la Base de Données
Modifiez les identifiants dans `bdconnect.php` (Web) et `ServeurSocket.java` (Java) si nécessaire.
Actuellement configuré pour : `postgresql-shoeser.alwaysdata.net`.

### 2. Connexion au site web:
exemple pour un magasin:
gérant: id:`c-lbeguin`, mdp:`A123456*`.
manager: id:`c-dbeguin`, mdp:`A123456*`.
employé: id:`c-d-mokri`, mdp:`A123456*`

### 3. Lancement du Serveur Stock (Java)
Le serveur gère les connexions entrantes des terminaux.
Se situer dans le dossier ScannerSAE.
```bash
# Compilation (Assurez-vous d'avoir le .jar postgres dans le classpath)
javac src\Connexion\*.java
javac -d bin -cp "src\Connexion\postgresql-42.7.8.jar" src\Connexion\ServeurSocket.java

# Lancement (Port 50000 par défaut)
java -cp "src;src/Connexion/postgresql-42.7.8.jar" Connexion.ServeurSocket
```

## 📡 Protocole Socket (Terminal)

Le terminal communique avec le serveur via des messages textuels formatés. Voici les commandes disponibles :

| Commande | Syntaxe | Description |
| :--- | :--- | :--- |
| **Authentification** | `DEBUT;idMagasin` | Initialise la session pour un magasin donné. |
| **Scan** | `SCAN;codeBarre;taille` | Récupère le prix et le stock via code-barres. |
| **Saisie Manuelle** | `SAISIE;idChaussure;taille` | Récupère les infos via l'ID interne. |
| **Mise à jour** | `MAJ_STOCK;id;qte;taille` | Modifie la quantité d'un produit. |
| **Déconnexion** | `FIN` | Ferme la session proprement. |

##  📂 Arborescence du Projet

```text
shoeser/
├── PartieSocket/
│   ├── clientSocket.py           # Client Python (Terminal magasin)
│   └── ScannerSAE/
│       ├── bin/                  # Binaires compilés Java
│       └── src/
│           └── Connexion/
│               ├── postgresql-42.7.8.jar  # Driver JDBC
│               └── ServeurSocket.java     # Serveur Java
├── siteShoeser/
│   ├── css/                      # Styles (Dark/Light)
│   ├── images/                   # Ressources graphiques
│   ├── include/                  # PHP Includes (DB, Header, Footer)
│   ├── secret/                   # Clés de sécurité CSV
│   ├── acc.php                   # Accueil
│   ├── index.php                 # Login
│   ├── stock.php                 # Dashboard Gérant
│   └── ... (autres fichiers PHP)
├── SQL/
│   ├── CREATE-TABLE_SHOESER.sql
│   └── SELECT-REQUEST_SHOESER.sql
└── README.md
```
## 🎓 Auteurs

Projet réalisé par :

* **Beguin Loris**
* **Bonacorsi Léa**
* **Mokri Dyhia**

CY CERGY-PARIS UNIVERSITÉ
