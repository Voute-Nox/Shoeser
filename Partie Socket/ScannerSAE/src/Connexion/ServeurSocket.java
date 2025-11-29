package Connexion;

import java.net.SocketTimeoutException;
import java.io.BufferedReader;
import java.io.BufferedWriter;
import java.io.IOException;
import java.io.InputStreamReader;
import java.io.OutputStreamWriter;
import java.net.ServerSocket;
import java.net.Socket;
import java.sql.*;
import java.util.Locale;

/**
 * Classe ServeurSocket.
 * Serveur applicatif de Shoeser qui communique avec un client en TCP
 * et avec une base PostgreSQL pour gérer le stock.
 */
public class ServeurSocket {

    /** Port TCP sur lequel le serveur écoute. */
	private final int portEcoute;

    /** Connexion à la base de données. */
    private Connection connexionBD;
    
    /** Taille maximale d'un message applicatif (en caractères). */
    private static final int TAILLE_MAX_LIGNE = 512;
    
    /**
     * Constructeur du serveur.
     *
     * @param portEcoute port TCP sur lequel le serveur doit écouter
     */
    public ServeurSocket(int portEcoute) {
        this.portEcoute = portEcoute;
    }

    /**
     * Méthode main qui lance le serveur.
     *
     * @param args args[0] peut contenir le port d'écoute
     */
    public static void main(String[] args) {
        System.out.println("Serveur Shoeser démarré (protocole TCP) !");

        int port = 50000;

        if (args.length >= 1) {
            try {
                port = Integer.parseInt(args[0]);
            } catch (NumberFormatException e) {
                System.err.println("Port invalide en paramètre, utilisation du port par défaut 50000.");
            }
        }

        new ServeurSocket(port).lancerServeur();
    }

    /**
     * Lance le serveur : connexion à la BD puis mise en écoute sur le port TCP.
     */
    private void lancerServeur() {
        try {
            connecterBD();
            demarrerEcoute();
        } catch (SQLException e) {
            System.err.println("Erreur de connexion BD : " + e.getMessage());
            e.printStackTrace();
        }
    }

    /**
     * Établit la connexion JDBC à la base PostgreSQL.
     *
     * @throws SQLException si la connexion échoue
     */
    private void connecterBD() throws SQLException {
        String url = "jdbc:postgresql://postgresql-shoeser.alwaysdata.net:5432/shoeser_base"; 
        String utilisateur = "shoeser";                         
        String motDePasse = "mycy234";                           

        connexionBD = DriverManager.getConnection(url, utilisateur, motDePasse);
        System.out.println("Connexion BD établie");
    }

    /**
     * Démarre la boucle d'écoute : accepte les connexions clients
     * et les traite une par une.
     */
    private void demarrerEcoute() {
    	try (ServerSocket socketServeur = new ServerSocket(portEcoute)) {
    	    System.out.println("Serveur en écoute sur le port " + portEcoute);

            while (true) {
                Socket socketClient = socketServeur.accept();
                System.out.println("Client connecté : " + socketClient.getInetAddress());
                gererClient(socketClient);
            }

        } catch (IOException e) {
            System.err.println("Erreur serveur : " + e.getMessage());
            e.printStackTrace();
        }
    }

    /**
     * Gère le dialogue avec un client.
     * Les commandes reconnues sont : DEBUT, SCAN, SAISIE, MAJ_STOCK et FIN.
     *
     * @param socketClient socket du client connecté
     */
    private void gererClient(Socket socketClient) {
        
    	
    	try {
            // Timeout de lecture : 45 secondes
            socketClient.setSoTimeout(45000);
        } catch (IOException e) {
            System.err.println("Impossible de configurer le timeout du client : " + e.getMessage());
            return;
        }

        try (
            BufferedReader entree = new BufferedReader(
                    new InputStreamReader(socketClient.getInputStream())
            );
            BufferedWriter sortie = new BufferedWriter(
                    new OutputStreamWriter(socketClient.getOutputStream())
            )
        ) {
            String ligne;
            boolean enCours = true;

            Integer idMagasin = null;

            while (enCours && (ligne = entree.readLine()) != null) {

                if (ligne.length() > TAILLE_MAX_LIGNE) {
                    System.err.println("Message trop long reçu, fermeture de la connexion.");
                    envoyerLigne(sortie, "ERR;TAILLE_MESSAGE");
                    break;
                }

                System.out.println("Reçu : " + ligne);

                String[] donnees = ligne.split(";");
                String commande = donnees[0].toUpperCase(Locale.ROOT);

                switch (commande) {
                    case "DEBUT":
                        idMagasin = traiterDebut(donnees, sortie);
                        break;
                    case "SCAN":
                        traiterScan(donnees, idMagasin, sortie);
                        break;
                    case "SAISIE":
                        traiterSaisie(donnees, idMagasin, sortie);
                        break;
                    case "MAJ_STOCK":
                        traiterMajStock(donnees, idMagasin, sortie);
                        break;
                    case "FIN":
                        envoyerLigne(sortie, "BYE");
                        enCours = false;
                        break;
                    default:
                        envoyerLigne(sortie, "ERR;COMMANDE_INCONNUE");
                        break;
                }
            }

        } catch (SocketTimeoutException e) {
            System.err.println("Client trop lent, fermeture de la connexion : " + e.getMessage());
        } catch (IOException e) {
            System.err.println("Erreur I/O avec le client : " + e.getMessage());
        } finally {
            try { socketClient.close(); } catch (IOException ignored) {}
            System.out.println("Client déconnecté.");
        }
    }

    /**
     * Envoie une ligne de texte au client.
     *
     * @param sortie flux de sortie vers le client
     * @param message message à envoyer
     * @throws IOException si une erreur d'écriture survient
     */
    private void envoyerLigne(BufferedWriter sortie, String message) throws IOException {
        sortie.write(message);
        sortie.newLine();
        sortie.flush();
    }

    /**
     * Traite la commande d'ouverture de session.
     * Exemple de message attendu : "DEBUT;1"
     * (ici 1 est l'id du magasin)
     *
     * @param donnees éléments de la ligne reçue (séparés par ';')
     * @param sortie flux de sortie vers le client
     * @return l'id du magasin si le message est correct, null sinon
     * @throws IOException si une erreur d'écriture survient
     */
    private Integer traiterDebut(String[] donnees, BufferedWriter sortie) throws IOException {
        if (donnees.length != 2) {
            envoyerLigne(sortie, "ERR;FORMAT_DEBUT");
            return null;
        }

        try {
            int idMagasin = Integer.parseInt(donnees[1]);

            envoyerLigne(sortie, "DEBUT_OK");
            return idMagasin;

        } catch (NumberFormatException e) {
            envoyerLigne(sortie, "ERR;FORMAT_DEBUT");
            return null;
        }
    }

    /**
     * Traite un scan de code-barres.
     * Exemple de message attendu : "SCAN;1234567890123"
     * (ici 1234567890123 est le code-barres lu par le scanner)
     *
     * @param donnees éléments de la ligne reçue
     * @param idMagasin identifiant du magasin (doit avoir été défini par DEBUT)
     * @param sortie flux de sortie vers le client
     * @throws IOException si une erreur d'écriture survient
     */
    private void traiterScan(String[] donnees, Integer idMagasin, BufferedWriter sortie) throws IOException {
        if (idMagasin == null) {
            envoyerLigne(sortie, "ERR;PAS_DE_DEBUT");
            return;
        }
        if (donnees.length != 3) {
            envoyerLigne(sortie, "ERR;FORMAT_SCAN");
            return;
        }

        String codeBarre = donnees[1];

        int taille;
        try {
            taille = Integer.parseInt(donnees[2]);
        } catch (NumberFormatException e) {
            envoyerLigne(sortie, "ERR;FORMAT_SAISIE");
            return;
        }

        String requeteSQL =
                "SELECT id_chaussure, prix, quantite, taille " +
                "FROM LigneStock " +
                "JOIN Chaussure USING(id_chaussure) " +
                "WHERE code_barre = ? AND id_magasin = ? AND taille = ?";

        try (PreparedStatement requete = connexionBD.prepareStatement(requeteSQL)) {
            requete.setString(1, codeBarre);
            requete.setInt(2, idMagasin);
            requete.setInt(3, taille);

            try (ResultSet resultat = requete.executeQuery()) {
                if (!resultat.next()) {
                    envoyerLigne(sortie, "INCONNU");
                    return;
                }

                int idChaussure = resultat.getInt("id_chaussure");
                double prix = resultat.getDouble("prix");
                int quantite = resultat.getInt("quantite");

                if (quantite > 0) {
                    envoyerLigne(sortie, "OK;" + idChaussure + ";" + prix);
                } else {
                    envoyerLigne(sortie, "RUPTURE;" + idChaussure);
                }
            }

        } catch (SQLException e) {
            System.err.println("Erreur SQL dans SCAN : " + e.getMessage());
            envoyerLigne(sortie, "ERR;SERVEUR");
        }
    }

    /**
     * Traite la saisie manuelle d'une chaussure.
     * Exemple de message attendu : "SAISIE;42"
     * (ici 42 est l'id de la chaussure saisi par l'utilisateur)
     *
     * @param donnees éléments de la ligne reçue
     * @param idMagasin identifiant du magasin
     * @param sortie flux de sortie vers le client
     * @throws IOException si une erreur d'écriture survient
     */
    private void traiterSaisie(String[] donnees, Integer idMagasin, BufferedWriter sortie) throws IOException {
        if (idMagasin == null) {
            envoyerLigne(sortie, "ERR;PAS_DE_DEBUT");
            return;
        }
        if (donnees.length != 3) {
            envoyerLigne(sortie, "ERR;FORMAT_SAISIE");
            return;
        }

        int idChaussure;
        try {
            idChaussure = Integer.parseInt(donnees[1]);
        } catch (NumberFormatException e) {
            envoyerLigne(sortie, "ERR;FORMAT_SAISIE");
            return;
        }

        int taille;
        try {
            taille = Integer.parseInt(donnees[2]);
        } catch (NumberFormatException e) {
            envoyerLigne(sortie, "ERR;FORMAT_SAISIE");
            return;
        }

        String requeteSQL =
                "SELECT c.prix, ls.quantite, ls.taille " +
                "FROM LigneStock ls " +
                "JOIN Chaussure c ON c.id_chaussure = ls.id_chaussure " +
                "WHERE ls.id_chaussure = ? AND ls.id_magasin = ? AND ls.taille = ?";

        try (PreparedStatement requete = connexionBD.prepareStatement(requeteSQL)) {
            requete.setInt(1, idChaussure);
            requete.setInt(2, idMagasin);
            requete.setInt(3, taille);

            try (ResultSet resultat = requete.executeQuery()) {
                if (!resultat.next()) {
                    envoyerLigne(sortie, "INCONNU");
                    return;
                }

                double prix = resultat.getDouble("prix");
                int quantite = resultat.getInt("quantite");

                if (quantite > 0) {
                    envoyerLigne(sortie, "OK;" + prix);
                } else {
                    envoyerLigne(sortie, "RUPTURE;" + idChaussure);
                }
            }

        } catch (SQLException e) {
            System.err.println("Erreur SQL dans SAISIE : " + e.getMessage());
            envoyerLigne(sortie, "ERR;SERVEUR");
        }
    }

    /**
     * Traite la mise à jour du stock par un manager.
     * Exemple de message attendu : "MAJ_STOCK;42;10"
     * (ici 42 est l'id de la chaussure et 10 la nouvelle quantité en stock)
     *
     * @param donnees éléments de la ligne reçue
     * @param idMagasin identifiant du magasin
     * @param sortie flux de sortie vers le client
     * @throws IOException si une erreur d'écriture survient
     */
    private void traiterMajStock(String[] donnees, Integer idMagasin, BufferedWriter sortie) throws IOException {
        if (idMagasin == null) {
            envoyerLigne(sortie, "ERR;PAS_DE_DEBUT");
            return;
        }
        if (donnees.length != 4) {
            envoyerLigne(sortie, "ERR;FORMAT_MAJ");
            return;
        }

        int idChaussure;

        int taille;
        try {
            taille = Integer.parseInt(donnees[3]);
        } catch (NumberFormatException e) {
            envoyerLigne(sortie, "ERR;FORMAT_SAISIE");
            return;
        }

        int nouvelleQuantite;
        try {
            idChaussure = Integer.parseInt(donnees[1]);
            nouvelleQuantite = Integer.parseInt(donnees[2]);
        } catch (NumberFormatException e) {
            envoyerLigne(sortie, "ERR;FORMAT_MAJ");
            return;
        }

        if (nouvelleQuantite < 0) {
            envoyerLigne(sortie, "MAJ_ERR;QUANTITE_INVALIDE");
            return;
        }

        try {
            String requeteSelectionSQL =
                    "SELECT quantite FROM LigneStock " +
                    "WHERE id_chaussure = ? AND id_magasin = ? AND taille = ?";
            try (PreparedStatement requeteSelection = connexionBD.prepareStatement(requeteSelectionSQL)) {
                requeteSelection.setInt(1, idChaussure);
                requeteSelection.setInt(2, idMagasin);
                requeteSelection.setInt(3, taille);
                try (ResultSet resultat = requeteSelection.executeQuery()) {
                    if (!resultat.next()) {
                        envoyerLigne(sortie, "MAJ_ERR;INCONNU");
                        return;
                    }
                }
            }

            String requeteMiseAJourSQL =
                    "UPDATE LigneStock " +
                    "SET quantite = ? " +
                    "WHERE id_chaussure = ? AND id_magasin = ? AND taille = ?";
            try (PreparedStatement requeteMiseAJour = connexionBD.prepareStatement(requeteMiseAJourSQL)) {
                requeteMiseAJour.setInt(1, nouvelleQuantite);
                requeteMiseAJour.setInt(2, idChaussure);
                requeteMiseAJour.setInt(3, idMagasin);
                requeteMiseAJour.setInt(4, taille);

                int lignesAffectees = requeteMiseAJour.executeUpdate();
                if (lignesAffectees == 1) {
                    envoyerLigne(sortie, "MAJ_OK;" + nouvelleQuantite);
                } else {
                    envoyerLigne(sortie, "MAJ_ERR;INCONNU");
                }
            }

        } catch (SQLException e) {
            System.err.println("Erreur SQL dans MAJ_STOCK : " + e.getMessage());
            envoyerLigne(sortie, "ERR;SERVEUR");
        }
    }
}