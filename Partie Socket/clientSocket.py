import socket
import sys

# Configuration
ADRESSE_IP = "127.0.0.1"
PORT = 50000

def afficher_aide():
    print("-" * 50)
    print("COMMANDES DISPONIBLES :")
    print("1. Démarrer session :  DEBUT;idMagasin")
    print("   Exemple : DEBUT;1")
    print("")
    print("2. Scanner article :   SCAN;codeBarre")
    print("   Exemple : SCAN;1234567890123")
    print("")
    print("3. Saisie manuelle :   SAISIE;idChaussure;taille")
    print("   Exemple : SAISIE;42;43")
    print("")
    print("4. Mise à jour stock : MAJ_STOCK;idChaussure;quantite;taille")
    print("   Exemple : MAJ_STOCK;42;10;43")
    print("")
    print("5. Quitter :           FIN")
    print("-" * 50)

def demarrer_client():
    try:
        client = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
        client.connect((ADRESSE_IP, PORT))
        print(f" Connecté au serveur sur {ADRESSE_IP}:{PORT}")
    except ConnectionRefusedError:
        print(f" Impossible de se connecter. Vérifiez que le serveur Java tourne bien sur le port {PORT}.")
        return

    afficher_aide()

    try:
        while True:
            # 1. Lecture de la saisie utilisateur
            try:
                message = input("Vous > ")
            except EOFError:
                # Gère le Ctrl+D
                break

            # Si vide, on ignore
            if not message.strip():
                continue

            # Commandes de sortie locales
            if message.lower() in ["quit", "exit"]:
                print("Fermeture demandée...")
                break

            # 2. Envoi au serveur (Ajout impératif du \n pour le readLine() Java)
            msg_a_envoyer = message + "\n"
            client.sendall(msg_a_envoyer.encode("utf-8"))

            # Si on envoie FIN, on s'attend à recevoir BYE puis on coupe
            if message.strip().upper() == "FIN":
                print("Envoi de la commande de fin...")

            # 3. Réception de la réponse
            try:
                reponse = client.recv(1024)
            except ConnectionResetError:
                print(" Le serveur a réinitialisé la connexion.")
                break

            if not reponse:
                print(" Le serveur a fermé la connexion.")
                break

            # Décodage et affichage propre
            reponse_str = reponse.decode("utf-8").strip()
            print(f"Serveur < {reponse_str}")

            if reponse_str == "BYE":
                break

    except KeyboardInterrupt:
        print("\nArrêt manuel (Ctrl+C).")
    finally:
        client.close()
        print("Connexion fermée.")

if __name__ == "__main__":
    demarrer_client()