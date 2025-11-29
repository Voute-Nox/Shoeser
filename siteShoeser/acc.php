<?php
ob_start();
?>
<!DOCTYPE html>
<html lang="fr" style="scroll-behavior: smooth;">
	<?php 
		$title = "Accueil";
		$description = "page accueil";
		$h1 = "";
			
		require"./include/header.inc.php";

		// Si pas connecté, redirige vers
		if (!isset($_SESSION['email']) && !estUtilisateurValide($_SESSION['email'])) {
			header("Location: index.php");
			exit();
		}
	?>

	<main>
		<?php shoesCard();?>
	</main>
	
	<script>
		document.addEventListener("DOMContentLoaded", function () {
			var acc = document.getElementsByClassName("accordion");

			for (let i = 0; i < acc.length; i++) {
				const btn = acc[i];
				const sectionId = "accordion_" + i; // identifiant unique

				// --- Restaurer l'état sauvegardé ---
				const saved = localStorage.getItem(sectionId);
				if (saved === "hidden") {
					toggleSection(btn, false);
				}

				btn.addEventListener("click", function () {
					var next = this.nextElementSibling;
					let show = true;

					// On regarde l'état actuel de l'article (le plus fiable)
					while (next && next.tagName !== "ARTICLE") {
						next = next.nextElementSibling;
					}
					if (next && next.style.display === "none") {
						show = true;  // on veut réafficher
					} else {
						show = false; // on veut masquer
					}

					toggleSection(this, show);

					// Sauvegarder dans localStorage
					localStorage.setItem(sectionId, show ? "visible" : "hidden");
				});
			}

			function toggleSection(button, show) {
				let next = button.nextElementSibling;

				// Masque/Affiche jusqu’à l’article
				while (next && next.tagName !== "ARTICLE") {
					if (next.tagName === "DIV") {
						next.style.display = show ? "block" : "none";
					}
					next = next.nextElementSibling;
				}

				if (next && next.tagName === "ARTICLE") {
					next.style.display = show ? "block" : "none";
				}

				button.textContent = show ? "Masquer" : "Afficher";
			}
		});
</script>



	<?php
		require "./include/footer.inc.php";
	?>
</html>