<?php
$title = "Inscription";
$description = "Page d'inscription";
$h1 = "";

require_once "./include/functions.inc.php";

$style = 'standard';
session_start();

if (isset($_SESSION['email'])) {
    header("Location: acc.php");
    exit();
} else {
    session_unset();
    session_destroy();
}

// Définir CSS, toggle et logo selon le style choisi
if ($style === 'alternatif') {
    $cssFile = 'css/dark.css';
    $toggleStyle = 'standard';
    $toggleImage = 'soleil.png';
    $logo = 'logo2.png';
} else {
    $cssFile = 'css/style.css';
    $toggleStyle = 'alternatif';
    $toggleImage = 'lune.png';
    $logo = 'logo.png';
}

$valide = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Vérification de la présence des champs
    if (isset($_POST['nom'], $_POST['prenom'], $_POST['naissance'], $_POST['adresse'], $_POST['mail'], $_POST['contrat'], $_POST['role'], $_POST['password'], $_POST['password2'], $_POST['key'], $_POST['id_magasin'])) {
        
        // 1. Récupération et nettoyage des variables (hors transaction car sans risque)
        $nom = strtolower(htmlspecialchars($_POST['nom']));
        $prenom = strtolower(htmlspecialchars($_POST['prenom']));
        $naissance = htmlspecialchars($_POST['naissance']);
        $adresse = htmlspecialchars($_POST['adresse']);
        $mail = htmlspecialchars($_POST['mail']);
        $contrat = htmlspecialchars($_POST['contrat']);
        $role = htmlspecialchars($_POST['role']);
        $password = $_POST['password'];
        $password2 = $_POST['password2'];
        $key = htmlspecialchars($_POST['key']);
        $id_magasin = intval($_POST['id_magasin']);
        // Création de l'ID personnalisé
        $id = "c-" . $prenom[0] . substr($nom, 0, 8);

        // Préparation des variables spécifiques aux rôles
        $caisse = $rayon = $stage = false;
        $date_nomination = '';
        $region = '';

        if ($role === 'user') {
            $caisse = !empty($_POST['Caisse']);
            $rayon  = !empty($_POST['Rayon']);
            $stage  = !empty($_POST['Stage']);
        } elseif ($role === 'manag') {
            $date_nomination = $_POST['date_nomination'] ?? '';
        } elseif ($role === 'admin') {
            $region = $_POST['region'] ?? '';
            $date_nomination = $_POST['date_nomination'] ?? '';
        }

        // 2. Début du bloc de logique critique
        try {
			global $pdo;
			$pdo->beginTransaction(); // DÉBUT TRANSACTION

			// --- A. LES VÉRIFICATIONS ---
			if ($password !== $password2) {
				throw new Exception("Les mots de passe ne correspondent pas.");
			}

			$file2 = 'secret/.htaccess/key.csv';
			if ($key !== getClefAcces($file2, $role)) {
				throw new Exception("Clé d'accès erronée !");
			}

			$stmt = $pdo->prepare("SELECT idutilisateur FROM utilisateur WHERE mail = ?");
			$stmt->execute([$mail]);
			if ($stmt->rowCount() > 0) {
				throw new Exception("Cet adresse email est déjà utilisée !");
			}

			if (!userExist($id)) {
				throw new Exception("Un compte existe déjà avec l'identifiant ($id) !");
			}

			if ($role === 'admin') {
				$stmt = $pdo->prepare("SELECT id_utilisateur_gerant FROM Magasin WHERE id_magasin = ?");
				$stmt->execute([$id_magasin]);
				$existingGerant = $stmt->fetchColumn();
				if ($existingGerant) {
					throw new Exception("Ce magasin a déjà un gérant !");
				}
			}

			// --- B. INSERTIONS ---

			// 1. Création utilisateur
			addUser($nom, $prenom, $naissance, $adresse, $mail, $contrat, $id, $password);
			
			$id_utilisateur = idUser($id);
			if (!$id_utilisateur) { throw new Exception("Erreur ID utilisateur."); }

			// 2. Gestion des rôles
			if ($role === 'admin') {
				// --- CAS DU GÉRANT ---
				addGerant($id_utilisateur, $region, $date_nomination);
				
				// a. On dit au magasin que C'EST lui le nouveau patron
				$stmt = $pdo->prepare("UPDATE Magasin SET id_utilisateur_gerant = ? WHERE id_magasin = ?");
				$stmt->execute([$id_utilisateur, $id_magasin]);
				
				// b. On met à jour les Managers de ce magasin
				// Logic : Si un manager n'a pas de chef (NULL) OU a un chef qui n'est pas celui déclaré dans Magasin
				// On le remplace par le nouveau.
				$sql = "UPDATE Manager 
						SET id_gerant = ? 
						WHERE id_magasin = ? 
						AND (
							id_gerant IS NULL 
							OR 
							id_gerant NOT IN (
								SELECT id_utilisateur_gerant 
								FROM Magasin 
								WHERE id_magasin = ?
							)
						)";

				$stmt = $pdo->prepare($sql);
				// Paramètres : 1. Le nouveau Gérant, 2. ID Magasin (Where), 3. ID Magasin (Sous-requête)
				$stmt->execute([$id_utilisateur, $id_magasin, $id_magasin]);

			} elseif ($role === 'manag') {
				// --- CAS DU MANAGER ---
				$stmt = $pdo->prepare("SELECT id_utilisateur_gerant FROM Magasin WHERE id_magasin = ?");
				$stmt->execute([$id_magasin]);
				$id_gerant = $stmt->fetchColumn() ?: null;
				
				addManag($id_utilisateur, $date_nomination, $id_gerant, $id_magasin);
				
				// Logic : Récupérer les employés orphelins ou mal assignés
				$sql = "UPDATE Employe 
						SET id_utilisateur_manager = ? 
						WHERE id_magasin = ? 
						AND (
							id_utilisateur_manager IS NULL 
							OR 
							id_utilisateur_manager NOT IN (
								SELECT id_utilisateur_manager 
								FROM Manager 
								WHERE id_magasin = ?
							)
						)";
				
				$stmt = $pdo->prepare($sql);
				$stmt->execute([$id_utilisateur, $id_magasin, $id_magasin]);

			} else { 
				// --- CAS DE L'EMPLOYÉ ---
				addEmploye($id_utilisateur, $rayon, $caisse, $stage, null, $id_magasin);
				
				$stmt = $pdo->prepare("SELECT id_utilisateur_manager FROM Manager WHERE id_magasin = ? LIMIT 1");
				$stmt->execute([$id_magasin]);
				$id_manager = $stmt->fetchColumn();
				
				if ($id_manager) {
					$stmt = $pdo->prepare("UPDATE Employe SET id_utilisateur_manager = ? WHERE id_utilisateur_employe = ?");
					$stmt->execute([$id_manager, $id_utilisateur]);
				}
			}

			// --- C. VALIDATION ---
			$pdo->commit(); 
			$valide = "Inscription réussie ! <br>Identifiant: $id";

		} catch (Exception $e) {
			$pdo->rollBack();
			$error = $e->getMessage();
		}
    }
}
?>

<!DOCTYPE html>
<html lang="fr" style="scroll-behavior: smooth;">
<head>
    <link rel="stylesheet" href="<?= $cssFile ?>"/>
    <link rel="icon" href="images/icon.png" type="image/png" />
    <title><?= $title ?></title>
    <meta charset='utf-8' />
    <meta name="author" content="Beguin Loris, Bonacorsi Léa, Mokri Dyhia" />
    <meta name="description" content="<?= $description ?>"/>
</head>
<body style="background-color: rgb(202,156,116);background-image: url('images/logo.png');background-position: center;background-attachment: fixed;margin: 0;display: flex;min-height: 100vh;align-items: center;flex-direction: row;">
    <h1><?= $h1 ?></h1>
    <main>
        <section style="background-color: transparent;">
            <article>
                <div class="login-box">
                    <h2>Inscription</h2>

                    <!-- Affichage des messages -->
                    <?php
                    if ($error !== "") {
                        echo "<p style='color: red;'>$error</p>";
                    } elseif ($valide !== "") {
                        echo "<p style='color: green;'>$valide</p>";
                    }
                    ?>

                    <form class="Connex-form" method="post" action="">
                        <input type="text" name="nom" placeholder="Nom" required>
                        <input type="text" name="prenom" placeholder="Prénom" required>
                        <p>Date de naissance :</p>
                        <input type="date" name="naissance" required>
                        <input type="text" name="adresse" placeholder="Adresse postale" required>
                        <input type="email" name="mail" placeholder="Adresse mail" required>

                        <p>Type de contrat :</p>
                        <select name="contrat" required>
                            <option value="CDI">CDI</option>
                            <option value="CDD">CDD</option>
                            <option value="Stage">Stage</option>
                        </select>

                        <p>Rôle :</p>
                        <select id="role" name="role" required>
                            <option value="">-- Sélectionnez un rôle --</option>
                            <option value="user">Employé</option>
                            <option value="manag">Manager</option>
                            <option value="admin">Responsable</option>
                        </select>

                        <p>Magasin :</p>
                        <select name="id_magasin" required>
                            <?php
                            $magasins = getMagasins();
                            foreach ($magasins as $mag) {
                                echo "<option value='{$mag['id_magasin']}'>{$mag['nom_magasin']}</option>";
                            }
                            ?>
                        </select>

                        <div id="extra-fields"></div>

                        <script>
                        document.getElementById('role').addEventListener('change', function() {
                            const container = document.getElementById('extra-fields');
                            const role = this.value;
                            container.innerHTML = '';

                            if (role === 'user') {
                                container.innerHTML = `
                                    <label><input type="checkbox" name="Caisse" value="1"> Caisse </label>
                                    <label><input type="checkbox" name="Rayon" value="1"> Rayon </label>
                                    <label><input type="checkbox" name="Stage" value="1"> Stage</label>
                                `;
                            } else if (role === 'manag') {
                                container.innerHTML = `<input type="date" name="date_nomination" required>`;
                            } else if (role === 'admin') {
                                container.innerHTML = `
                                    <input type="text" name="region" placeholder="Région responsable" required style="margin-bottom:10px;">
                                    <input type="date" name="date_nomination" required>
                                `;
                            }
                        });
                        </script>

                        <input type="password" name="password" placeholder="Mot de passe" required>
                        <input type="password" name="password2" placeholder="Valider mot de passe" required>
                        <p>-</p>
                        <input type="password" name="key" placeholder="Clé d'accès" required>
                        <button type="submit">S'inscrire</button>
                    </form>

                    <a href="index.php">Déjà un compte ?</a>
                </div>
            </article>
        </section>
    </main>
</body>
</html>
