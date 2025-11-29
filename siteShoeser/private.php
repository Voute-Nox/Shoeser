<!DOCTYPE html>
<html lang="fr" style="scroll-behavior: smooth;">
	<?php
	
		session_start(); // toujours commencer la session

		// Si pas connecté, redirige vers
		if (!isset($_SESSION['email'])) {
			header("Location: index.php");
			exit();
		}
		$title = "Page profil";
		$description = "Page profil";
		$h1 = "";
			
		require"./include/header.inc.php";
	?>

	<main>
		<section>
			<h2>Profil Personel</h2>
				<article>
					<div class="login-box" style="text-align: left;">
						<h2>Informations</h2>
						<p><span>Votre email/Identifiant :</span> <?php echo $_SESSION['email'] ?></p>
						<p><span style = "font-weight: bold;text-decoration: underline;">Nom :</span> <?php getNom($_SESSION['email']) ?></p> 
						<p><span style = "font-weight: bold;text-decoration: underline;">Prénom:</span> <?php getPrenom($_SESSION['email']) ?></p>
						<p><span style = "font-weight: bold;text-decoration: underline;">Date de naissance :</span> <?php getDateN($_SESSION['email']) ?></p>
						<p><span style = "font-weight: bold;text-decoration: underline;">Adresse postale :</span> <?php getAdrr($_SESSION['email']) ?></p>
						<p><span style = "font-weight: bold;text-decoration: underline;">E-mail :</span> <?php getMail($_SESSION['email']) ?></p>
						<p><span style = "font-weight: bold;text-decoration: underline;">Contrat :</span> <?php getContrat($_SESSION['email']) ?></p>
						<p><span style = "font-weight: bold;text-decoration: underline;">Date d'inscription :</span> <?php getDateI($_SESSION['email']) ?></p>
						
					</div>
				</article>
			</section>
	</main>

	<?php
		require "./include/footer.inc.php";
	?>
</html>