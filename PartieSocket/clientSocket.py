import socket
import sys
import threading
import os

# --- VALEURS PAR DÉFAUT ---
DEFAULT_IP = "127.0.0.1"
DEFAULT_PORT = 50000

def demander_config():
    """Demande à l'utilisateur de saisir l'IP et le port."""
    print("--- CONFIGURATION ---")
    
    # 1. Demande IP
    saisie_ip = input(f"Adresse IP du serveur [{DEFAULT_IP}] : ").strip()
    ip = saisie_ip if saisie_ip else DEFAULT_IP

    # 2. Demande Port
    saisie_port = input(f"Port du serveur [{DEFAULT_PORT}] : ").strip()
    if not saisie_port:
        port = DEFAULT_PORT
    else:
        try:
            port = int(saisie_port)
        except ValueError:
            print(f" Port invalide. Utilisation du port par défaut {DEFAULT_PORT}.")
            port = DEFAULT_PORT
            
    return ip, port

def afficher_aide():
    print("-" * 50)
    print("COMMANDES DISPONIBLES :")
    print("1. DEBUT;idMagasin")
    print("2. SCAN;codeBarre;taille")
    print("3. SAISIE;idChaussure;taille")
    print("4. MAJ_STOCK;idChaussure;quantite;taille")
    print("5. FIN")
    print("-" * 50)

def ecouter_serveur(client):
    """
    Thread d'écoute : Reçoit les messages du serveur.
    """
    while True:
        try:
            data = client.recv(1024)
            
            # Si data est vide, le serveur a coupé la connexion
            if not data:
                print("\n\n Le serveur a coupé la connexion (Arrêt immédiat).")
                os._exit(0) 

            message = data.decode("utf-8").strip()
            
            # Affichage propre
            print(f"\rServeur < {message}")
            
            if message == "BYE":
                print("Fermeture demandée. Appuyez sur Entrée pour quitter.")
                client.close()
                os._exit(0)

            print("Vous > ", end='', flush=True)
            
        except ConnectionResetError:
            print("\n\n Connexion perdue brusquement.")
            os._exit(0)
        except Exception:
            break

def demarrer_client():
    # --- ETAPE 1 : CONFIGURATION ---
    ip_cible, port_cible = demander_config()

    client = socket.socket(socket.AF_INET, socket.SOCK_STREAM)
    
    # --- ETAPE 2 : CONNEXION SÉCURISÉE ---
    print(f"...Tentative de connexion vers {ip_cible}:{port_cible} (5s max)...")
    
    # On définit un temps limite de 3 secondes pour se connecter
    client.settimeout(5)

    try:
        client.connect((ip_cible, port_cible))
        
        # IMPORTANT : Une fois connecté, on enlève le timeout pour ne pas être déconnecté
        client.settimeout(None) 
        print(f" CONNECTÉ AVEC SUCCÈS !")
        
    except socket.timeout:
        print(f" ERREUR : Délai d'attente dépassé (Timeout).")
        print(f"   -> L'IP {ip_cible} est-elle bonne ? le port {port_cible} est-il bon?")
        return # On arrête proprement ici
    except ConnectionRefusedError:
        print(f" ERREUR : Connexion refusée.")
        print(f"   -> Le serveur n'est PAS lancé sur le port {port_cible}.")
        return
    except socket.gaierror:
        print(f" ERREUR : Adresse IP invalide ('{ip_cible}').")
        return
    except OSError as e:
        print(f" ERREUR RÉSEAU : {e}")
        return

    # --- ETAPE 3 : LANCEMENT DU PROGRAMME ---
    afficher_aide()

    # 1. On lance le Thread d'écoute
    thread_ecoute = threading.Thread(target=ecouter_serveur, args=(client,), daemon=True)
    thread_ecoute.start()

    # 2. Boucle d'envoi
    print("Vous > ", end='', flush=True)
    
    while True:
        try:
            message = input()
            
            if not message.strip():
                print("Vous > ", end='', flush=True)
                continue

            if message.lower() in ["quit", "exit"]:
                break

            client.sendall((message + "\n").encode("utf-8"))
            
        except EOFError:
            break
        except OSError:
            break # Si le socket est mort, on sort

    client.close()
    print("Fin du programme.")

if __name__ == "__main__":
    try:
        demarrer_client()
    except KeyboardInterrupt:
        print("\nArrêt utilisateur.")