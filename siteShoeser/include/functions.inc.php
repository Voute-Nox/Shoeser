<?php  declare(strict_types=1); 
	
	include __DIR__ . "/../../config/bdconnect.php";
	
	/**
	 * Récupère la clé d'accès pour l'inscription d'un utilisateur.
	 *
	 * Cette fonction renvoie la clé correspondant au rôle de l'utilisateur :
	 * - "employe"
	 * - "manager"
	 * - "gerant"
	 *
	 * @return string La clé d'accès correspondant au rôle.
	 */
	function getClefAcces($fichier, $case) {
		if (($handle = fopen($fichier, "r")) !== false) {
			while (($data = fgetcsv($handle, 1000, ",")) !== false) {
				if (strcasecmp($data[0], $case) == 0) { // compare sans tenir compte de la casse
					fclose($handle);
					return $data[1]; // retourne la clef d'accès
				}
			}
			fclose($handle);
		}
		return null; // pas trouvé
	}

	/**
	 * Verifie l'existance d'un utilisateur dans la BD
	 *
	 * @return bool vrai si l'utilisateur n'existe pas dans la BD
	 */
	function userExist($pseudo){
		global $pdo;
		$stmt = $pdo->prepare("SELECT idutilisateur FROM utilisateur WHERE pseudo = :pseudo");
		$stmt->execute(['pseudo' => $pseudo]);
		return $stmt->rowCount() === 0;
	}
	
	/**
	 * Verifie le mot de passe utilisateur
	 *
	 * @return bool vrai si le mot de passe correspond à celui rentrée dans la BD
	 */
	function goodPassword($pseudo, $mdp) {
		global $pdo;
		$stmt = $pdo->prepare("SELECT mot_de_passe FROM utilisateur WHERE pseudo = :pseudo");
		$stmt->execute(['pseudo' => $pseudo]);
		return ($row = $stmt->fetch()) && password_verify($mdp, $row['mot_de_passe']);
	}
	
	/**
	 * Ajoute un utilisateur à la BD à partir des informations transmises par la page inscription
	 *
	 */
	function addUser($nom,$prenom,$naissance,$adresse,$mail,$contrat,$id,$password){
		global $pdo;
		
		$passwordHash = password_hash($password, PASSWORD_DEFAULT);
		
		$stmt = $pdo->prepare("
			INSERT INTO utilisateur (nom, prenom, date_de_naissance, adresse_postale, mail, type_contrat, pseudo, mot_de_passe)
			VALUES (:nom, :prenom, :naissance, :adresse, :mail, :contrat, :pseudo, :mot_de_passe)
		");
		
		$stmt->execute([
			'nom' => $nom,
			'prenom' => $prenom,
			'naissance' => $naissance,
			'adresse' => $adresse,
			'mail' => $mail,
			'contrat' => $contrat,
			'pseudo' => $id,
			'mot_de_passe' => $passwordHash
		]);
	}
	
	/**
	 * Ajoute un Gérant à la BD
	 *
	 */
	function addGerant($id,$region,$date_nomination){
		global $pdo;
		
		$stmt = $pdo->prepare("
			INSERT INTO gerant (id_utilisateur_gerant,region,date_nomination)
			VALUES (:id, :region, :nomination)
		");
		
		$stmt->execute([
			'id' => $id,
			'region' => $region,
			'nomination' => $date_nomination
		]);
	}
	
	/**
	 * Ajoute un Manager à la BD
	 *
	 */
	function addManag($id_utilisateur, $date_nomination, $id_gerant, $id_magasin) {
		global $pdo;

		// On ajoute explicitement la colonne id_magasin dans la requête SQL
		$sql = "INSERT INTO Manager (id_utilisateur_manager, date_nomination, id_gerant, id_magasin) 
				VALUES (?, ?, ?, ?)";
				
		$stmt = $pdo->prepare($sql);
		
		// On exécute avec les 4 variables
		$stmt->execute([$id_utilisateur, $date_nomination, $id_gerant, $id_magasin]);
	}
	
	/**
	 * Ajoute un Employe à la BD
	 *
	 */
	function addEmploye($id, $est_rayon, $est_caisse, $est_stagiaire, $idManager = null, $idMagasin = 1) {
		global $pdo;

		$stmt = $pdo->prepare("
			INSERT INTO employe (
				id_utilisateur_employe, id_utilisateur_manager,
				est_rayon, est_caisse, est_stagiaire, id_magasin
			)
			VALUES (:id_employe, :id_manager, :est_rayon, :est_caisse, :est_stagiaire, :id_magasin)
		");

		$stmt->bindValue(':id_employe', $id, PDO::PARAM_INT);
		$stmt->bindValue(':id_manager', $idManager, $idManager === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
		$stmt->bindValue(':est_rayon', (bool)$est_rayon, PDO::PARAM_BOOL);
		$stmt->bindValue(':est_caisse', (bool)$est_caisse, PDO::PARAM_BOOL);
		$stmt->bindValue(':est_stagiaire', (bool)$est_stagiaire, PDO::PARAM_BOOL);
		$stmt->bindValue(':id_magasin', $idMagasin, PDO::PARAM_INT);

		$stmt->execute();
	}
	
	/**
	 * recupere l'id d'un utilisateur à partir de son pseudo 
	 *
	 * @return int l'id utilisteur || null si aucun pseudo correspond à ceux présents dans la BD
	 */
	function idUser($pseudo){
		global $pdo;
		$stmt = $pdo->prepare("SELECT idutilisateur FROM utilisateur WHERE pseudo = :pseudo");
		$stmt->execute(['pseudo' => $pseudo]);
		
		$id_utilisateur = $stmt->fetchColumn();

		if ($id_utilisateur !== false) {
			return (int)$id_utilisateur;
		} else {
			return null;
		}
	}
	
	/**
	 * recupere l'id d'une chaussure à partir de son nom de modele
	 *
	 * @return int l'id chaussure || null si aucun nom de modele correspond à ceux présents dans la BD
	 */
	function idChaussure($nom){
		global $pdo;
		$stmt = $pdo->prepare("SELECT DISTINCT id_chaussure FROM chaussure WHERE nom_modele = :nom");
		$stmt->execute(['nom' => $nom]);
		
		$id_chaussure = $stmt->fetchColumn();

		if ($id_chaussure !== false) {
			return (int)$id_chaussure;
		} else {
			return null;
		}
	}
	
	/**
	 * renvoie les informations sur la matiere d'une chaussure à partir de son id
	 *
	 * @return Array de l'ensemble des informations de la matiere d'une chaussure
	 */
	function matiereChaussure($idChaussure){
		global $pdo;

		$stmt = $pdo->prepare("SELECT DISTINCT id_materiau FROM composition WHERE id_chaussure = :id");
		$stmt->execute(['id' => $idChaussure]);
		$id_materiau = $stmt->fetchColumn();

		if ($id_materiau !== false) {
			$stmt2 = $pdo->prepare("SELECT DISTINCT nom, est_impermeable FROM materiau WHERE id_materiau = :id");
			$stmt2->execute(['id' => $id_materiau]);
			$infoMatiere = $stmt2->fetch(PDO::FETCH_ASSOC); // fetch car on a qu’une ligne
			return $infoMatiere;
		} else {
			return null;
		}
	}

	
	/**
	 * renvoie la liste des chaussures de la BD à partir du nom du modele
	 *
	 * @return Array de l'ensemble des noms de chaussures present dans la BD
	 */
	function shoesList(){
		global $pdo;
		$stmt = $pdo->prepare("SELECT DISTINCT nom_modele FROM chaussure");
		$stmt->execute();
		$chaussures = $stmt->fetchAll(PDO::FETCH_COLUMN);
		return $chaussures;
	}
	
	/**
	 * renvoie la liste d'informations des chaussures de la BD à partir du nom du modele
	 *
	 * @return Array de l'ensemble informations des chaussures present dans la BD
	 */
	function shoesInfo($chaussures) {
		global $pdo;
		$infoChaussures = [];

		foreach ($chaussures as $nom) {
			$stmt = $pdo->prepare("
				SELECT DISTINCT sexe, poids, couleur, type_chaussure, forme, hauteur_talon, id_marque, code_barre
				FROM chaussure 
				WHERE nom_modele = :nom
			");
			$stmt->execute(['nom' => $nom]);

			$result = $stmt->fetch(PDO::FETCH_ASSOC);

			if ($result) {
				// Stocker dans le tableau final avec le nom comme clé
				$infoChaussures[$nom] = [
					"sexe" => $result['sexe'],
					"poids" => $result['poids'],
					"couleur" => $result['couleur'],
					"type_chaussure" => $result['type_chaussure'],
					"forme" => $result['forme'],
					"hauteur_talon" => $result['hauteur_talon'],
					"id_marque" => $result['id_marque'],
					"code_barre" => $result['code_barre']
				];
			}
		}

		return $infoChaussures;
	}
	
	/**
	 * renvoie le prix de la chaussure donnée
	 *
	 * @return Float le prix de l'id d'une chaussure donnée
	 */
	function prixChaussure($id){
		global $pdo;
		$stmt = $pdo->prepare("SELECT DISTINCT prix FROM chaussure WHERE id_chaussure = :id");
		$stmt->execute(['id' => $id]);
		$prix = $stmt->fetchColumn();
		return $prix;
	}
	
	/**
	 * affiche un symbole pour indiquer que le seuil limite est atteind
	 */
	function estEnAlerte($idChaussure, $taille, $idMagasin){
		global $pdo;

		// Ajout de AND id_magasin = :idMagasin
		$stmt = $pdo->prepare("SELECT quantite, seuil_alerte FROM lignestock WHERE id_chaussure = :id AND taille = :taille AND id_magasin = :idMagasin");
		$stmt->execute(['id' => $idChaussure, 'taille' => $taille, 'idMagasin' => $idMagasin]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($row) {
			if( $row['quantite'] < $row['seuil_alerte']){
				return "<span style ='color:red'> !</span>";
			}
		}
		return "";
	}
	
	/**
	 * affiche le tableaux des tailles (Lecture Seule)
	 */
	function tabTailleReadOnly($idChaussure, $idMagasin) {
		global $pdo;

		// Récupération des tailles du magasin spécifique
		$stmt = $pdo->prepare("SELECT taille, quantite 
							   FROM lignestock 
							   WHERE id_chaussure = ? 
							   AND id_magasin = ? 
							   ORDER BY taille ASC");
		$stmt->execute([$idChaussure, $idMagasin]);
		$tailles = $stmt->fetchAll(PDO::FETCH_ASSOC);

		// Si aucune donnée pour ce magasin (cas rare mais possible)
		if (empty($tailles)) {
			return "<div style='color:gray; font-size:12px;'>Aucun stock pour ce magasin.</div>";
		}

		// HTML compact
		$html = "<div>";
		$html .= "<table style='border-collapse:collapse; width:100%; font-size:13px;'>";
		
		$cols = 3;
		$i = 0;
		$html .= "<tr>";

		foreach ($tailles as $t) {
			$taille = $t['taille'];
			$q      = $t['quantite'];

			// On passe aussi l'ID magasin à la fonction d'alerte
			$alerte = estEnAlerte($idChaussure, $taille, $idMagasin);

			$html .= "
			<td style='border:1px solid #ccc; padding:5px; text-align:center;'>
				<div><b>$taille</b>$alerte</div>
				<div>$q en stock</div>
			</td>
			";

			$i++;
			if ($i % $cols == 0) $html .= "</tr><tr>";
		}

		$html .= "</tr></table></div>";

		return $html;
	}
	
	/**
	 * affiche le tableaux des tailles avec modification
	 */
	function tabTaille($idChaussure, $idMagasin) {
		global $pdo;

		$msg = '';

		// --- Mise à jour si paramètres GET présents ---
		if (isset($_GET['update'], $_GET['t'], $_GET['d'])) {
			$u_id     = intval($_GET['update']);
			$u_taille = intval($_GET['t']);
			$u_diff   = intval($_GET['d']);

			if ($u_id === intval($idChaussure)) {
				// IMPORTANT : On vérifie aussi l'id_magasin pour récupérer la bonne ligne
				$stmt = $pdo->prepare("SELECT id_ligne_stock, quantite FROM lignestock WHERE id_chaussure=? AND taille=? AND id_magasin=?");
				$stmt->execute([$u_id, $u_taille, $idMagasin]);
				$row = $stmt->fetch(PDO::FETCH_ASSOC);

				if ($row !== false) {
					$idLigneStock = $row['id_ligne_stock'];
					$current = $row['quantite'];
					$new = max(0, $current + $u_diff);

					// --- Mise à jour de la quantité ---
					// Ici id_ligne_stock est unique, donc pas besoin de remettre id_magasin dans le UPDATE, 
					// mais on a sécurisé sa récupération juste au-dessus.
					$stmt = $pdo->prepare("UPDATE lignestock SET quantite=? WHERE id_ligne_stock=?");
					$stmt->execute([$new, $idLigneStock]);
					$msg = "Taille $u_taille mise à jour : $new stock.";

					// --- Log modification Manager ---
					if (isset($_SESSION['email'])) {
						$email = $_SESSION['email'];
						$stmt = $pdo->prepare("SELECT idutilisateur FROM utilisateur WHERE pseudo=?");
						$stmt->execute([$email]);
						$idUser = $stmt->fetchColumn();

						if ($idUser) { 
							$motif = "Modification stock magasin $idMagasin via interface";

							// --- CORRECTION ICI ---
							// 1. On vérifie d'abord si cet utilisateur est bien dans la table 'manager'
							$stmtCheck = $pdo->prepare("SELECT count(*) FROM manager WHERE id_utilisateur_manager = ?");
							$stmtCheck->execute([$idUser]);
							$isManager = $stmtCheck->fetchColumn();

							if ($isManager > 0) {
								// C'est un Manager : On peut insérer sans erreur
								$stmt = $pdo->prepare("
									INSERT INTO Modification (id_manager, id_ligne_stock, motif)
									VALUES (?, ?, ?)
									ON CONFLICT (id_manager, id_ligne_stock) DO UPDATE
									SET horodatage_modif = CURRENT_TIMESTAMP, motif = EXCLUDED.motif
								");
								$stmt->execute([$idUser, $idLigneStock, $motif]);
							} else {
								// C'est probablement un Gérant.
								// Si ta table 'Modification' a une colonne 'id_gerant', tu dois faire un INSERT différent ici.
								// Sinon, on ne fait rien pour éviter le crash.
								
								// Exemple (si tu as une colonne id_gerant) :
								/*
								$stmt = $pdo->prepare("INSERT INTO Modification (id_gerant, id_ligne_stock, motif) VALUES (?, ?, ?)...");
								$stmt->execute([$idUser, $idLigneStock, $motif]);
								*/
							}
						}
					}
				}
			}
		}

		// --- Récupération des tailles du magasin ---
		$stmt = $pdo->prepare("SELECT taille, quantite FROM lignestock WHERE id_chaussure=? AND id_magasin=? ORDER BY taille ASC");
		$stmt->execute([$idChaussure, $idMagasin]);
		$tailles = $stmt->fetchAll(PDO::FETCH_ASSOC);

		if (empty($tailles)) {
			return "<div style='color:gray; font-size:12px;'>Aucun stock défini pour ce magasin.</div>";
		}

		// --- HTML compact ---
		$html = "<div>";
		if ($msg) $html .= "<p style='color:green;margin:4px 0;'>$msg</p>";

		$html .= "<table style='border-collapse:collapse; width:100%; font-size:13px;'>";
		$cols = 3; 
		$i = 0;
		$html .= "<tr>";

		foreach ($tailles as $t) {
			$taille = $t['taille'];
			$q      = $t['quantite'];

			$minus = $_SERVER['PHP_SELF']."?update=$idChaussure&t=$taille&d=-1";
			$plus  = $_SERVER['PHP_SELF']."?update=$idChaussure&t=$taille&d=1";

			$html .= "
			<td style='border:1px solid #ccc; padding:5px; text-align:center;'>
				<div><b>$taille</b>".estEnAlerte($idChaussure, $taille, $idMagasin)."</div>
				<div>$q en stock</div>
				<div>
					<a href='$minus' style='font-weight:bold;font-size:16px; text-decoration:none;'>-</a>
					&nbsp;
					<a href='$plus' style='font-weight:bold;font-size:16px; text-decoration:none;'>+</a>
				</div>
			</td>
			";

			$i++;
			if ($i % $cols == 0) $html .= "</tr><tr>";
		}

		$html .= "</tr></table></div>";
		return $html;
	}

	/**
	* Cree l'affichage de la carte de chaque chaussure avec leurs informations
	*/
	function shoesCard() {
		global $pdo;

		$chaussures = shoesList();
		$infos = shoesInfo($chaussures);

		// Valeurs par défaut
		$roleUtilisateur = 'client'; // Par sécurité, défaut à client
		$canModify = false;
		$idMagasinUser = 0; // Aucun magasin par défaut

		// Récupération email session
		$email = $_SESSION['email'] ?? null;

		if ($email) {
			// Récupérer l'id utilisateur correspondant au pseudo
			$stmt = $pdo->prepare("SELECT idutilisateur FROM utilisateur WHERE pseudo = ?");
			$stmt->execute([$email]);
			$idUser = $stmt->fetchColumn();

			if ($idUser) {
				// Définir l'ordre de priorité et récupérer l'ID MAGASIN en même temps
				$roles = [
					'manager' => ['table' => 'manager', 'col' => 'id_utilisateur_manager'],
					'gerant'  => ['table' => 'gerant',  'col' => 'id_utilisateur_gerant'], // Gérant n'a pas forcément id_magasin direct dans certaines tables, à vérifier selon ton schéma. 
					// Si gerant est lié au magasin via la table Magasin, il faudra adapter. 
					// Je suppose ici que la table 'gerant' n'a PAS id_magasin, mais que 'Magasin' a id_gerant.
					// MAIS pour ton schéma précédent (Manager a id_magasin, Employe a id_magasin), utilisons cette logique :
					
					'employe' => ['table' => 'employe', 'col' => 'id_utilisateur_employe']
				];

				// 1. Vérif Manager ou Employé (qui ont id_magasin dans leur table)
				foreach (['manager', 'employe'] as $r) {
					$info = $roles[$r];
					// On sélectionne id_magasin direct
					$stmt = $pdo->prepare("SELECT id_magasin FROM {$info['table']} WHERE {$info['col']} = ?");
					$stmt->execute([$idUser]);
					$result = $stmt->fetch(PDO::FETCH_ASSOC);

					if ($result) {
						$roleUtilisateur = $r;
						$idMagasinUser = $result['id_magasin'];
						break;
					}
				}

				// 2. Cas spécifique du Gérant (Souvent lié via la table Magasin)
				if ($roleUtilisateur === 'client') {
					 // On regarde si c'est un gérant dans la table Magasin
					 $stmt = $pdo->prepare("SELECT id_magasin FROM Magasin WHERE id_utilisateur_gerant = ?");
					 $stmt->execute([$idUser]);
					 $magasinGerant = $stmt->fetchColumn();
					 
					 if ($magasinGerant) {
						 $roleUtilisateur = 'gerant';
						 $idMagasinUser = $magasinGerant;
					 }
				}
			}
		}

		// Si on a pas trouvé de magasin pour cet utilisateur, on arrête ou on affiche un message
		if ($idMagasinUser == 0) {
			echo "<h3 style='color:red; text-align:center'>Vous n'êtes assigné à aucun magasin. Impossible d'afficher les stocks.</h3>";
			return; 
		}

		// Déterminer droit de modification
		// Manager et Gerant peuvent modifier
		$canModify = in_array($roleUtilisateur, ['manager', 'gerant']);

		foreach ($chaussures as $nom) {
			// Récupération des infos de la marque
			$stmt = $pdo->prepare("SELECT nom_marque FROM marque WHERE id_marque = :id");
			$stmt->execute(['id' => $infos[$nom]["id_marque"]]);
			$nomMarque = $stmt->fetchColumn();

			$idChaussure = idChaussure($nom);
			$prix = prixChaussure($idChaussure);
			$infoMatiere = matiereChaussure($idChaussure);

			// Affichage HTML
			echo '
			<section>
				<button class="accordion">Masquer</button>
				<div class="slideshow-container" id="slideshow-'.$idChaussure.'">
					<div class="mySlides slide-'.$idChaussure.' fade">
						<div class="numbertext">1 / 3</div>
						<img src="images/'.$idChaussure.'-1.jpg" style="width:100%;padding-bottom:20px;">
					</div>
					 <div class="mySlides slide-'.$idChaussure.' fade">
						<div class="numbertext">2 / 3</div>
						<img src="images/'.$idChaussure.'-2.jpg" style="width:100%;padding-bottom:20px;">
					</div>
					 <div class="mySlides slide-'.$idChaussure.' fade">
						<div class="numbertext">3 / 3</div>
						<img src="images/'.$idChaussure.'-3.jpg" style="width:100%;padding-bottom:20px;">
					</div>
					<a class="prev" onclick="plusSlides(-1, '.$idChaussure.')">&#10094;</a>
					<a class="next" onclick="plusSlides(1, '.$idChaussure.')">&#10095;</a>
				</div>

				<div style="text-align:center">
					<span class="dot dot-'.$idChaussure.'" onclick="currentSlide(1, '.$idChaussure.')"></span>
					<span class="dot dot-'.$idChaussure.'" onclick="currentSlide(2, '.$idChaussure.')"></span>
					<span class="dot dot-'.$idChaussure.'" onclick="currentSlide(3, '.$idChaussure.')"></span>
				</div>

				<h2>'.htmlspecialchars($nom).' - '.$prix.'€</h2>
				<article>
					<h3>'.ucwords($nomMarque).'</h3>
					<hr class="barre3"/>
					';
					
					// --- MODIFICATION ICI : APPEL DES FONCTIONS AVEC L'ID MAGASIN ---
					if ($canModify) {
						echo tabTaille($idChaussure, $idMagasinUser);
					} else {
						echo tabTailleReadOnly($idChaussure, $idMagasinUser);
					}

			echo '
					<ul>
						<li style="color:red;">Id : '.$idChaussure.'</li>
						<li>Code barre : '.$infos[$nom]["code_barre"].'</li>
						<li>Couleur : '.$infos[$nom]["couleur"].'</li>
						<li>Sexe : '.$infos[$nom]["sexe"].'</li>
						<li>Poids : '.$infos[$nom]["poids"].'g</li>
						<li>Forme : '.$infos[$nom]["forme"].'</li>
						<li>Type de chaussure : '.$infos[$nom]["type_chaussure"].'</li>
						<li>Hauteur du Talon : '.$infos[$nom]["hauteur_talon"].'cm</li>
						<li>Matière : '.($infoMatiere && isset($infoMatiere['nom']) ? $infoMatiere['nom'] : 'Inconnue').'</li>
						<li>Impèrméable : '. (
							!empty($infoMatiere) && isset($infoMatiere['est_impermeable']) && $infoMatiere['est_impermeable']? 'Oui': 'Non') . 
						'</li>
					</ul>
				</article>
			</section>';
		}

		// Le script JS reste identique
		echo '
		<script>
			function plusSlides(n, id) {
				let container = document.getElementById("slideshow-"+id);
				if(!container) return;
				let slides = container.getElementsByClassName("slide-"+id);
				let index = parseInt(container.dataset.index) || 1;
				index += n;
				if(index > slides.length) index = 1;
				if(index < 1) index = slides.length;
				container.dataset.index = index;
				showSlides(slides, index);
			}

			function currentSlide(n, id) {
				let container = document.getElementById("slideshow-"+id);
				if(!container) return;
				let slides = container.getElementsByClassName("slide-"+id);
				container.dataset.index = n;
				showSlides(slides, n);
			}

			function showSlides(slides, n) {
				if(slides.length === 0) return;
				let container = slides[0].parentElement;
				let dots = container.nextElementSibling.getElementsByClassName("dot-"+container.id.split("-")[1]);
				for(let i=0;i<slides.length;i++) slides[i].style.display = "none";
				for(let i=0;i<dots.length;i++) dots[i].className = dots[i].className.replace(" active","");
				slides[n-1].style.display = "block";
				if(dots[n-1]) dots[n-1].className += " active";
			}

			document.addEventListener("DOMContentLoaded", () => {
				let containers = document.querySelectorAll(".slideshow-container");
				containers.forEach(container => {
					let id = container.id.split("-")[1];
					let slides = container.getElementsByClassName("slide-"+id);
					container.dataset.index = 1;
					showSlides(slides, 1);
				});
			});
		</script>';
	}
	
	function getPrenom($pseudo){
		global $pdo;

		$stmt = $pdo->prepare("SELECT prenom FROM utilisateur WHERE pseudo = :p");
		$stmt->execute(['p' => $pseudo]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($row) {
			echo $row['prenom'];
		}
	}
	
	function getDateN($pseudo){
		global $pdo;

		$stmt = $pdo->prepare("SELECT date_de_naissance FROM utilisateur WHERE pseudo = :p");
		$stmt->execute(['p' => $pseudo]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($row) {
			echo $row['date_de_naissance'];
		}
	}
	
	function getAdrr($pseudo){
		global $pdo;

		$stmt = $pdo->prepare("SELECT adresse_postale FROM utilisateur WHERE pseudo = :p");
		$stmt->execute(['p' => $pseudo]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($row) {
			echo $row['adresse_postale'];
		}
	}
	
	function getMail($pseudo){
		global $pdo;

		$stmt = $pdo->prepare("SELECT mail FROM utilisateur WHERE pseudo = :p");
		$stmt->execute(['p' => $pseudo]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($row) {
			echo $row['mail'];
		}
	}
	
	function getContrat($pseudo){
		global $pdo;

		$stmt = $pdo->prepare("SELECT type_contrat FROM utilisateur WHERE pseudo = :p");
		$stmt->execute(['p' => $pseudo]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($row) {
			echo $row['type_contrat'];
		}
	}

	function getDateI($pseudo){
		global $pdo;

		$stmt = $pdo->prepare("SELECT date_inscription FROM utilisateur WHERE pseudo = :p");
		$stmt->execute(['p' => $pseudo]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		if ($row) {
			echo $row['date_inscription'];
		}
	}
	
	function getIdByPseudo($pseudo){
		global $pdo;

		$stmt = $pdo->prepare("SELECT idutilisateur FROM utilisateur WHERE pseudo = :p LIMIT 1");
		$stmt->execute(['p' => $pseudo]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ? (int)$row['idutilisateur'] : null;
	}

	
	function isGerant($id){
		global $pdo;

		// Convertir en entier (correct)
		$id = (int)$id;

		$stmt = $pdo->prepare("
			SELECT id_utilisateur_gerant
			FROM gerant
			WHERE id_utilisateur_gerant = :p
			LIMIT 1
		");

		$stmt->execute(['p' => $id]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ? true : false;
	}
	
	function isManager($id){
		global $pdo;

		// Convertir en entier (correct)
		$id = (int)$id;

		$stmt = $pdo->prepare("
			SELECT id_utilisateur_manager
			FROM manager
			WHERE id_utilisateur_manager = :p
			LIMIT 1
		");

		$stmt->execute(['p' => $id]);
		$row = $stmt->fetch(PDO::FETCH_ASSOC);

		return $row ? true : false;
	}
	
	function listeChaussures() {
		global $pdo;

		// Si on demande à supprimer une chaussure
		if (isset($_GET['del'])) {
			$id = intval($_GET['del']);
			
			// 1) Supprimer dans composition
			$stmt = $pdo->prepare("DELETE FROM composition WHERE id_chaussure = ?");
			$stmt->execute([$id]);

			// Supprimer d'abord les stocks liés à la chaussure
			$stmt = $pdo->prepare("DELETE FROM lignestock WHERE id_chaussure=?");
			$stmt->execute([$id]);

			// Puis supprimer la chaussure
			$stmt = $pdo->prepare("DELETE FROM chaussure WHERE id_chaussure=?");
			$stmt->execute([$id]);
		}

		// Récupérer toutes les chaussures
		$stmt = $pdo->query("SELECT id_chaussure, nom_modele FROM chaussure");
		$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

		// Affichage
		echo "<ul>";
		foreach ($rows as $c) {
			echo "<li>"
				. htmlspecialchars($c['nom_modele'])
				. " <a href='?del=" . $c['id_chaussure'] . "'>Supprimer</a>"
				. "</li>";
		}
		echo "</ul>";
	}
	
	function estUtilisateurValide($pseudo) {
		global $pdo;

		// Sécurisé et force chaîne
		$pseudo = strval($pseudo);

		$stmt = $pdo->prepare("SELECT statut_utilisateur FROM Utilisateur WHERE pseudo = ?");
		$stmt->execute([$pseudo]);
		$statut = $stmt->fetchColumn();

		return ($statut === 'valide');
	}

	function getMagasins() {
		global $pdo; // suppose que tu as un PDO global
		$stmt = $pdo->query("SELECT id_magasin, nom_magasin FROM Magasin ORDER BY nom_magasin");
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}


?>

