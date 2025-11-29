package Connexion;

import java.io.*;
import java.net.ServerSocket;
import java.net.Socket;
import java.net.SocketTimeoutException;
import java.sql.*;
import java.util.Locale;
import java.util.concurrent.atomic.AtomicBoolean;

/**
 * Classe ServeurSocket.
 * Modifiée pour n'accepter qu'UN SEUL client à la fois.
 * Tout autre client reçoit "ERR;SERVEUR_OCCUPE" et est déconnecté.
 */
public class ServeurSocket {

    private final int portEcoute;

    private Connection connexionBD;
    
    private static final int TAILLE_MAX_LIGNE = 512;
    
    private final AtomicBoolean estOccupe = new AtomicBoolean(false);
    
    public ServeurSocket(int portEcoute) {
        this.portEcoute = portEcoute;
    }

    public static void main(String[] args) {
        System.out.println("--- SERVEUR SHOESER (MODE MONO-CLIENT) ---");

        int port = 50000;
        if (args.length >= 1) {
            try {
                port = Integer.parseInt(args[0]);
            } catch (NumberFormatException e) {
                System.err.println("Port invalide, utilisation du port par défaut 50000.");
            }
        }

        new ServeurSocket(port).lancerServeur();
    }

    private void lancerServeur() {
        try {
            connecterBD();
            demarrerEcoute();
        } catch (SQLException e) {
            System.err.println(" Erreur de connexion BD : " + e.getMessage());
            e.printStackTrace();
        }
    }

    private void connecterBD() throws SQLException {
        String url = "jdbc:postgresql://postgresql-shoeser.alwaysdata.net:5432/shoeser_base"; 
        String utilisateur = "shoeser";                         
        String motDePasse = "mycy234";                           

        connexionBD = DriverManager.getConnection(url, utilisateur, motDePasse);
        System.out.println(" Connexion BD établie");
    }

    /**
     * Boucle principale (Le Videur).
     * Elle ne traite pas le client, elle ne fait que l'accueillir ou le rejeter.
     */
    private void demarrerEcoute() {
        try (ServerSocket socketServeur = new ServerSocket(portEcoute)) {
            System.out.println("🎧 Serveur en écoute sur le port " + portEcoute);

            while (true) {
                Socket socketEntrant = socketServeur.accept();

                if (estOccupe.get()) {
                    System.out.println(" Connexion rejetée (Serveur occupé) : " + socketEntrant.getInetAddress());
                    
                    try (BufferedWriter out = new BufferedWriter(new OutputStreamWriter(socketEntrant.getOutputStream()))) {
                        out.write("ERR;SERVEUR_OCCUPE");
                        out.newLine();
                        out.flush();
                    } catch (IOException ignored) {}
                    
                    socketEntrant.close();
                    
                } else {
                    // CAS B : SERVEUR LIBRE
                    System.out.println(" Nouveau client accepté : " + socketEntrant.getInetAddress());
                    
                    // On verrouille la place
                    estOccupe.set(true);

                    new Thread(() -> {
                        try {
                            gererClient(socketEntrant);
                        } finally {
                            estOccupe.set(false);
                            System.out.println("✅ Client parti, serveur LIBRE pour le suivant.");
                        }
                    }).start();
                }
            }

        } catch (IOException e) {
            System.err.println("Erreur serveur : " + e.getMessage());
            e.printStackTrace();
        }
    }

    /**
     * Gère le dialogue avec un client (Exécuté dans un Thread à part).
     */
    private void gererClient(Socket socketClient) {
        
        try {
            socketClient.setSoTimeout(60000); // 60 sec d'inactivité max
        } catch (IOException e) {
            System.err.println("Erreur timeout : " + e.getMessage());
            return;
        }

        try (
            BufferedReader entree = new BufferedReader(new InputStreamReader(socketClient.getInputStream()));
            BufferedWriter sortie = new BufferedWriter(new OutputStreamWriter(socketClient.getOutputStream()))
        ) {
            String ligne;
            boolean enCours = true;
            Integer idMagasin = null;

            while (enCours && (ligne = entree.readLine()) != null) {

                if (ligne.length() > TAILLE_MAX_LIGNE) {
                    System.err.println("Message trop long reçu.");
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
            System.err.println("Client trop lent (Timeout).");
        } catch (IOException e) {
            System.err.println("Connexion interrompue : " + e.getMessage());
        } finally {
            try { socketClient.close(); } catch (IOException ignored) {}
        }
    }

    private void envoyerLigne(BufferedWriter sortie, String message) throws IOException {
        sortie.write(message);
        sortie.newLine();
        sortie.flush();
    }

    // --- VOS MÉTHODES MÉTIERS (INCHANGÉES) ---

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

        String requeteSQL = "SELECT id_chaussure, prix, quantite, taille FROM LigneStock " +
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
            System.err.println("Erreur SQL SCAN : " + e.getMessage());
            envoyerLigne(sortie, "ERR;SERVEUR");
        }
    }

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
        int taille;
        try {
            idChaussure = Integer.parseInt(donnees[1]);
            taille = Integer.parseInt(donnees[2]);
        } catch (NumberFormatException e) {
            envoyerLigne(sortie, "ERR;FORMAT_SAISIE");
            return;
        }

        String requeteSQL = "SELECT c.prix, ls.quantite, ls.taille FROM LigneStock ls " +
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
            System.err.println("Erreur SQL SAISIE : " + e.getMessage());
            envoyerLigne(sortie, "ERR;SERVEUR");
        }
    }

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
        int nouvelleQuantite;
        int taille;

        try {
            idChaussure = Integer.parseInt(donnees[1]);
            nouvelleQuantite = Integer.parseInt(donnees[2]);
            taille = Integer.parseInt(donnees[3]);
        } catch (NumberFormatException e) {
            envoyerLigne(sortie, "ERR;FORMAT_MAJ");
            return;
        }

        if (nouvelleQuantite < 0) {
            envoyerLigne(sortie, "MAJ_ERR;QUANTITE_INVALIDE");
            return;
        }

        try {
            // Vérification de l'existence
            String checkSQL = "SELECT 1 FROM LigneStock WHERE id_chaussure = ? AND id_magasin = ? AND taille = ?";
            boolean existe = false;
            try(PreparedStatement pst = connexionBD.prepareStatement(checkSQL)) {
                pst.setInt(1, idChaussure);
                pst.setInt(2, idMagasin);
                pst.setInt(3, taille);
                try(ResultSet rs = pst.executeQuery()) {
                    if(rs.next()) existe = true;
                }
            }

            if(!existe) {
                envoyerLigne(sortie, "MAJ_ERR;INCONNU");
                return;
            }

            // Mise à jour
            String updateSQL = "UPDATE LigneStock SET quantite = ? " +
                               "WHERE id_chaussure = ? AND id_magasin = ? AND taille = ?";
            try (PreparedStatement pst = connexionBD.prepareStatement(updateSQL)) {
                pst.setInt(1, nouvelleQuantite);
                pst.setInt(2, idChaussure);
                pst.setInt(3, idMagasin);
                pst.setInt(4, taille);

                int lignes = pst.executeUpdate();
                if (lignes == 1) {
                    envoyerLigne(sortie, "MAJ_OK;" + nouvelleQuantite);
                } else {
                    envoyerLigne(sortie, "MAJ_ERR;INCONNU");
                }
            }
        } catch (SQLException e) {
            System.err.println("Erreur SQL MAJ : " + e.getMessage());
            envoyerLigne(sortie, "ERR;SERVEUR");
        }
    }
}