<?php
session_start(); // toujours commencer la session

// Si pas connecté, redirige vers
if (!isset($_SESSION['email'])) {
    header("Location: index.php");
    exit();
}

require "./include/functions.inc.php"; // pour isGerant si besoin

if (!isGerant(getIdByPseudo($_SESSION['email']))) {
    header("Location: acc.php");
    exit();
}

// Variables de page
$title = "Page profil";
$description = "Page profil";
$h1 = "";

?>

<!DOCTYPE html>
<html lang="fr" style="scroll-behavior: smooth;">
<?php require "./include/header.inc.php"; ?>


	<main>
		<section>
			<h2>Stock et Supervision</h2>
				<article>
					<div class="login-box" style="text-align: left;">
						<h2>Suppression chaussure</h2>
						<?listeChaussures()?>
					</div>
				</article>
				<article>
					<div class="login-box" style="text-align: left;">
						<h2>Ajout chaussure</h2>
						<?php
							// Récupérer les marques et matériaux
							$marques = $pdo->query("SELECT id_marque, nom_marque FROM Marque ORDER BY nom_marque ASC")
										   ->fetchAll(PDO::FETCH_ASSOC);
							$materiaux = $pdo->query("SELECT id_materiau, nom, est_impermeable FROM Materiau ORDER BY nom ASC")
											  ->fetchAll(PDO::FETCH_ASSOC);

							if ($_SERVER['REQUEST_METHOD'] === 'POST') {
								$errors = [];

								// Récupération et validation des champs
								$nom_modele     = trim($_POST['nom_modele']);
								$hauteur_talon  = $_POST['hauteur_talon'] !== '' ? floatval($_POST['hauteur_talon']) : null;
								$prix           = floatval($_POST['prix']);
								$forme          = trim($_POST['forme']);
								$type_chaussure = $_POST['type_chaussure'];
								$id_marque      = intval($_POST['id_marque']);
								$couleur        = trim($_POST['couleur']);
								$poids          = $_POST['poids'] !== '' ? floatval($_POST['poids']) : null;
								$sexe           = $_POST['sexe'];
								$idMateriau     = $_POST['id_materiau'] ?? null;

								// Vérifications des contraintes
								if ($prix < 10 || $prix > 3000) $errors[] = "Le prix doit être entre 10 et 3000 €.";
								if (!in_array($type_chaussure,['basket','talons','ville','sandale','bottine'])) $errors[] = "Type de chaussure invalide.";
								if (!in_array($sexe,['Homme','Femme','tout'])) $errors[] = "Sexe invalide.";
								if ($hauteur_talon !== null && ($hauteur_talon < 0 || $hauteur_talon > 15)) $errors[] = "Hauteur du talon entre 0 et 15 cm.";
								if ($poids !== null && ($poids < 100 || $poids > 5000)) $errors[] = "Poids entre 100 et 5000 g.";

								if (empty($errors)) {
									// Ajouter la chaussure
									$stmt = $pdo->prepare("
										INSERT INTO Chaussure
										(hauteur_talon, prix, nom_modele, forme, type_chaussure, id_marque, couleur, poids, sexe, code_barre)
										VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?,gen_random_uuid())
										RETURNING id_chaussure
									");
									$stmt->execute([$hauteur_talon, $prix, $nom_modele, $forme, $type_chaussure, $id_marque, $couleur, $poids, $sexe]);
									$idChaussure = $stmt->fetchColumn();

									// Ajouter le stock par taille (36 → 44 step 0.5)
									$tailles = range(36, 44, 0.5);
									$quantite = 10;
									$idMagasin = 1; // par défaut

									$stmtStock = $pdo->prepare("
										INSERT INTO LigneStock (quantite, taille, id_magasin, id_chaussure,seuil_alerte)
										VALUES (?, ?, ?, ?,10)
									");
									foreach ($tailles as $t) {
										if ($t > 26 && $t < 50) {
											$stmtStock->execute([$quantite, $t, $idMagasin, $idChaussure]);
										}
									}

									// Ajouter le matériau si choisi
									if ($idMateriau && $idMateriau !== 'none') {
										$stmt = $pdo->prepare("INSERT INTO Composition (id_chaussure, id_materiau) VALUES (?, ?)");
										$stmt->execute([$idChaussure, $idMateriau]);

										$stmt = $pdo->prepare("SELECT est_impermeable FROM Materiau WHERE id_materiau=?");
										$stmt->execute([$idMateriau]);
										$impermeable = $stmt->fetchColumn() ? 'Oui' : 'Non';
									} else {
										$impermeable = 'Ne sais pas';
									}

									echo "<p>Chaussure '$nom_modele' ajoutée avec 10 unités par taille. Matériau imperméable : $impermeable</p>";
								} else {
									echo '<p style="color:red">'.implode('<br>', $errors).'</p>';
								}
							}
							?>

							<form method="post">
								<label>Nom du modèle : <input type="text" name="nom_modele" required></label><br>
								<label>Hauteur talon (0-15 cm) : <input type="number" step="0.1" name="hauteur_talon"></label><br>
								<label>Prix (10-3000 €) : <input type="number" step="0.01" name="prix" required></label><br>
								<label>Forme : <input type="text" name="forme" required></label><br>
								<label>Type : 
									<select name="type_chaussure" required>
										<option value="basket">Basket</option>
										<option value="talons">Talons</option>
										<option value="ville">Ville</option>
										<option value="sandale">Sandale</option>
										<option value="bottine">Bottine</option>
									</select>
								</label><br>

								<label>Marque : 
									<select name="id_marque" required>
										<?php foreach ($marques as $m): ?>
											<option value="<?= $m['id_marque'] ?>"><?= htmlspecialchars($m['nom_marque']) ?></option>
										<?php endforeach; ?>
									</select>
								</label><br>

								<label>Couleur : <input type="text" name="couleur" required></label><br>
								<label>Poids (100-5000 g) : <input type="number" step="0.01" name="poids"></label><br>
								<label>Sexe : 
									<select name="sexe" required>
										<option value="Homme">Homme</option>
										<option value="Femme">Femme</option>
										<option value="tout">Tout</option>
									</select>
								</label><br>

								<label>Matériau : 
									<select name="id_materiau">
										<option value="none">Ne sais pas</option>
										<?php foreach ($materiaux as $mat): ?>
											<option value="<?= $mat['id_materiau'] ?>">
												<?= htmlspecialchars($mat['nom']) ?> (<?= $mat['est_impermeable'] ? 'Imperméable' : 'Non imperméable' ?>)
											</option>
										<?php endforeach; ?>
									</select>
								</label><br>

								<button class="button-pro" type="submit">Ajouter la chaussure</button>
							</form>


					</div>
				</article>
				<article>
					<div class="login-box" style="text-align: left;">
						<h2>Status employés</h2>
						<?php
							// 1. Récupération de l'ID connecté
							$idConnecteTemp = getIdByPseudo($_SESSION['email']);
							$idConnecte = ($idConnecteTemp !== null) ? intval($idConnecteTemp) : 0;

							// 2. Vérifier si c'est un Gérant ET récupérer son ID MAGASIN
							// On suppose que la table Magasin fait le lien (id_utilisateur_gerant -> id_magasin)
							$stmt = $pdo->prepare("SELECT id_magasin FROM Magasin WHERE id_utilisateur_gerant = ?");
							$stmt->execute([$idConnecte]);
							$idMagasinGerant = $stmt->fetchColumn(); // Renvoie l'ID du magasin ou false

							// Si ce n'est pas un gérant de magasin, on arrête ou on cache tout
							if (!$idMagasinGerant) {
								echo "<p>Vous n'êtes pas identifié comme gérant d'un magasin.</p>";
							} else {

								// --- Fonction pour créer un document de suivi (Inchangée) ---
								function creerDocumentTrace(PDO $pdo, int $idGerant, int $idUtilisateurConcerne, string $action) {
									if ($idGerant <= 0) return;
									$titre = "Utilisateur $action : " . date('d/m/Y H:i:s');
									$contenu = "L'utilisateur avec ID $idUtilisateurConcerne a été $action par le gérant ID $idGerant le " . date('d/m/Y H:i:s') . ".";
									$contenuBinaire = '\x' . bin2hex($contenu); // Format PostgreSQL BYTEA

									try {
										$stmt = $pdo->prepare("INSERT INTO Documents (titre, type_document, fichier_blob, id_gerant, id_utilisateur_concerne) VALUES (?, 'NOTE_INTERNE', ?, ?, ?)");
										$stmt->execute([$titre, $contenuBinaire, $idGerant, $idUtilisateurConcerne]);
									} catch (PDOException $e) {
										error_log("Erreur Trace : " . $e->getMessage());
									}
								}

								// --- Fonction de VÉRIFICATION D'APPARTENANCE (Sécurité URL) ---
								function estDansMonMagasin(PDO $pdo, int $idTarget, int $idMagasin) {
									// Vérifie si la cible est un Manager de ce magasin
									$stmt = $pdo->prepare("SELECT 1 FROM Manager WHERE id_utilisateur_manager = ? AND id_magasin = ?");
									$stmt->execute([$idTarget, $idMagasin]);
									if ($stmt->fetchColumn()) return true;

									// Vérifie si la cible est un Employé de ce magasin
									$stmt = $pdo->prepare("SELECT 1 FROM Employe WHERE id_utilisateur_employe = ? AND id_magasin = ?");
									$stmt->execute([$idTarget, $idMagasin]);
									if ($stmt->fetchColumn()) return true;

									return false;
								}

								// --- LOGIQUE DE BLOCAGE (Sécurisée) ---
								if (isset($_GET['block'])) {
									$idUser = intval($_GET['block']);
									
									// On vérifie d'abord si l'utilisateur appartient au magasin du gérant
									if ($idUser !== $idConnecte && estDansMonMagasin($pdo, $idUser, $idMagasinGerant)) {
										$stmt = $pdo->prepare("UPDATE Utilisateur SET statut_utilisateur='bloqué' WHERE idutilisateur=?");
										$stmt->execute([$idUser]);
										echo "<p style='color:red; font-weight:bold;'>Utilisateur bloqué avec succès.</p>";
										creerDocumentTrace($pdo, $idConnecte, $idUser, 'bloqué');
									} else {
										// Tentative de piratage via URL ou erreur
										echo "<p style='color:red;'>Erreur : Vous ne pouvez pas modifier cet utilisateur (pas dans votre magasin).</p>";
									}
								}

								// --- LOGIQUE DE DÉBLOCAGE (Sécurisée) ---
								if (isset($_GET['unblock'])) {
									$idUser = intval($_GET['unblock']);

									if ($idUser !== $idConnecte && estDansMonMagasin($pdo, $idUser, $idMagasinGerant)) {
										$stmt = $pdo->prepare("UPDATE Utilisateur SET statut_utilisateur='valide' WHERE idutilisateur=?");
										$stmt->execute([$idUser]);
										echo "<p style='color:green; font-weight:bold;'>Utilisateur débloqué avec succès.</p>";
										creerDocumentTrace($pdo, $idConnecte, $idUser, 'débloqué');
									} else {
										echo "<p style='color:red;'>Erreur : Vous ne pouvez pas modifier cet utilisateur.</p>";
									}
								}

								// --- AFFICHAGE (Filtré par Magasin uniquement) ---
								// On utilise UNION pour récupérer managers et employés DU MEME MAGASIN en une seule requête propre
								$sql = "
									SELECT u.idutilisateur, u.nom, u.prenom, u.statut_utilisateur, 'Manager' as role
									FROM Utilisateur u
									JOIN Manager m ON u.idutilisateur = m.id_utilisateur_manager
									WHERE m.id_magasin = ? AND u.idutilisateur != ?
									
									UNION
									
									SELECT u.idutilisateur, u.nom, u.prenom, u.statut_utilisateur, 'Employé' as role
									FROM Utilisateur u
									JOIN Employe e ON u.idutilisateur = e.id_utilisateur_employe
									WHERE e.id_magasin = ? AND u.idutilisateur != ?
									
									ORDER BY role DESC, nom, prenom
								";

								$stmt = $pdo->prepare($sql);
								// On passe les paramètres : idMagasin, idConnecte (pour manager), idMagasin, idConnecte (pour employe)
								$stmt->execute([$idMagasinGerant, $idConnecte, $idMagasinGerant, $idConnecte]);
								$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

								echo "<div style='max-width:600px; margin:20px auto; font-family:sans-serif;'>";
								echo "<h3>Gestion du personnel - Magasin N°$idMagasinGerant</h3>";

								if (count($users) === 0) {
									echo "<p>Aucun employé ou manager trouvé dans ce magasin.</p>";
								}

								foreach ($users as $u) {
									$id = $u['idutilisateur'];
									$nom = htmlspecialchars($u['nom']);
									$prenom = htmlspecialchars($u['prenom']);
									$statut = $u['statut_utilisateur'];
									$role = $u['role']; // Récupéré directement via le SQL

									// Bouton Bloquer/Débloquer
									$actionLink = ($statut === 'valide') 
										? "<a href='?block=$id' onclick=\"return confirm('Voulez-vous vraiment BLOQUER $nom $prenom ?');\" style='color:red; font-weight:bold; text-decoration:none;'>[Bloquer]</a>"
										: "<a href='?unblock=$id' onclick=\"return confirm('Voulez-vous vraiment DÉBLOQUER $nom $prenom ?');\" style='color:green; font-weight:bold; text-decoration:none;'>[Débloquer]</a>";

									// Couleur de fond légère pour différencier bloqué/actif
									$bgStyle = ($statut === 'bloqué') ? "background-color:#ffe6e6;" : "background-color:#fff;";

									echo "<div style='padding:10px; margin-bottom:8px; border:1px solid #ccc; border-radius:4px; display:flex; justify-content:space-between; align-items:center; $bgStyle'>
											<div>
												<span style='display:block; font-size:1.1em;'><b>$nom $prenom</b></span>
												<span style='font-size:0.9em; color:#555;'>$role - Statut: <i>$statut</i></span>
											</div>
											<div>$actionLink</div>
										  </div>";
								}

								echo "</div>";
							}
							?>

					</div>
				</article>
			</section>
	</main>

	<?php
		require "./include/footer.inc.php";
	?>
</html>