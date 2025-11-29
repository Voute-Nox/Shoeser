	<?php

	$title = "Connexion";
	$description = "page accueil";
	$h1 = " ";
	
	require_once "./include/functions.inc.php";
	
	$style = 'standard';
	
	session_start();
	
	if (isset($_SESSION['email'])) {
			header("Location: acc.php");
			exit();
		}
	else{
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

	$error = "";

	// Traitement du formulaire
	if ($_SERVER["REQUEST_METHOD"] === "POST") {
		if (isset($_POST['id'], $_POST['password'])) {
			$inputEmail = $_POST['id'];
			$inputPassword = $_POST['password'];
			
			if (userExist($inputEmail) || !goodPassword($inputEmail, $inputPassword)) {
				$error = "Id ou mot de passe incorrect";
			} else if (!estUtilisateurValide($inputEmail)) {
				$error = "Utilisateur bloqué";
			} else {
				session_start();
				$_SESSION['email'] = $inputEmail;
				$_SESSION['last_activity'] = time();

				// --- Récupérer l'id de l'utilisateur connecté ---
				$stmt = $pdo->prepare("SELECT idutilisateur FROM utilisateur WHERE pseudo = ?");
				$stmt->execute([$inputEmail]);
				$idUtilisateur = $stmt->fetchColumn();

				if ($idUtilisateur) {
					// Ajouter une consultation pour cet utilisateur
					$stmtInsert = $pdo->prepare("
						INSERT INTO Consultation (id_utilisateur)
						VALUES (?)
					");
					$stmtInsert->execute([$idUtilisateur]);
				}

				// Redirection vers private.php
				header("Location: acc.php");
				exit;
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
			<section style = "background-color: transparent;">
				<article>
					<div class="login-box">
					<h2>Connexion</h2>
						<?php if ($error !== ""): ?>
							<p style="color: red;"><?= $error ?></p>
						<?php endif; ?>

						<form class="Connex-form" method="post" action="index.php">
							<input type="text" name="id" placeholder="Identifiant" required>
							<input type="password" name="password" placeholder="Mot de passe" required>
							<button type="submit">Se connecter</button>
						</form>

						<a href="inscription.php">Créer un compte</a><br>
					</div>
				</article>
			</section>
		</main>
	</body>
	</html>
