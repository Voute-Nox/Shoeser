<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Gérer l'inactivité
$max_inactivity = 300; // 5min
if (isset($_SESSION['email']) && isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $max_inactivity) {
    session_unset();
    session_destroy();
    header("Location: index.php?timeout=1");
    exit;
}

// Mettre à jour le timestamp d'activité
if (isset($_SESSION['email'])) {
    $_SESSION['last_activity'] = time();
}

// Valeur par défaut
$style = "standard";

// Si un style est passé via l'URL et qu'il est valide
if (isset($_GET['style']) && in_array($_GET['style'], ['standard', 'alternatif'])) {
    $style = $_GET['style'];

    // On met à jour le cookie avec ce nouveau choix
    setcookie('GoodStyle', $style, time() + 60*60*24*30, '/');
}
// Sinon, on regarde s’il y a déjà un cookie de style
elseif (isset($_COOKIE['GoodStyle']) && in_array($_COOKIE['GoodStyle'], ['standard', 'alternatif'])) {
    $style = $_COOKIE['GoodStyle'];
}

// Définir le CSS, le style de bascule, l’image du toggle et le logo selon le style
if ($style === 'alternatif') {
    $cssFile = 'css/dark.css';
    $toggleStyle = 'standard';
    $toggleImage = 'soleil.png';
} else {
    $cssFile = 'css/style.css';
    $toggleStyle = 'alternatif';
    $toggleImage = 'lune.png';
}

require_once "./include/functions.inc.php";
?>



<head>
    <style>
        .bas-header {
            padding: 50px;
        }
    </style>

    <link rel="stylesheet" href="<?= $cssFile ?>"/>
    <link rel="icon" href="images/icon.png" type="image/png" />
    <title><?=$title?></title>
    <meta charset='utf-8' />
    <meta name="author" content="Beguin Loris" />
	<meta name="viewport" content="width=device-width, initial-scale=1"/>
    <?php echo "<meta name ='description' content ='$description'/>" ?>
</head>

<body style= "background-color: rgb(202,156,116);background-image: url('images/logo.png');background-position: center;background-attachment: fixed;"/>
    <a href="#">
        <img class="fleche" src="images/fleche.png" alt="image de fleche"/>
    </a>
	<script>
		// Affiche la flèche quand l'utilisateur scrolle
		const fleche = document.querySelector('.fleche');
		fleche.style.display = 'none';
		window.addEventListener('scroll', function() {
			if (window.scrollY > 100) { // tu peux ajuster la valeur
				fleche.style.display = 'block';
			} else {
				fleche.style.display = 'none';
			}
		});
	</script>

    <header>
		<nav class="menuHaut">
			<nav style="max-width: 1200px; display: flex; align-items: center;border-bottom: 2px solid #000;">
				<figure class="td-logo" style="margin: 0; padding: 0;">
					<a href="acc.php">
						<img class='logo-style' style='height: 75px;' src='images/logo.png' alt='logo étude' />
					</a>
				</figure>
				<p id="horloge"><p>
				<script>
					function afficherHeure() {
						const maintenant = new Date();
						const heures = String(maintenant.getHours()).padStart(2, '0');
						const minutes = String(maintenant.getMinutes()).padStart(2, '0');
						const secondes = String(maintenant.getSeconds()).padStart(2, '0');

						document.getElementById('horloge').textContent = `${heures}:${minutes}:${secondes}`;
					}

					// Appelle la fonction immédiatement pour ne pas attendre 1 seconde
					afficherHeure();

					// Met à jour toutes les secondes
					setInterval(afficherHeure, 1000);
				</script>
				<!--
				<form class="Rec-form" method="get" style="margin-left: auto;">
					<input type="text" name="q" placeholder="Rechercher..." required>
					<button type="submit">🔎</button>
				</form>
				-->
				</ul>
				<ul class="menu">
					<li class="right">
						<?php
						$params = $_GET;
						$params['style'] = $toggleStyle;
						$newQuery = http_build_query($params);
						?>
						<a class="aSpe" style="background-color: transparent;" href="<?= htmlspecialchars('?' . $newQuery, ENT_QUOTES | ENT_XML1) ?>">
							<img src="images/<?= $toggleImage ?>" alt="Changer le style"/>
						</a>         
					</li>
					<?php if (isset($_SESSION['email'])): ?>
						<?php if (isGerant(getIdByPseudo($_SESSION['email']))): ?>
							<li class="right"><a class="aSpe" href="stock.php">Supervision</a></li>
						<?php endif; ?>
						<li class="right"><a class="aSpe" href="private.php"><?php echo $_SESSION['email']; ?></a></li>
						<li class="right"><a class="aSpe" href="logout.php">Se déconnecter</a></li>
					<?php endif; ?>

				</ul>
			</nav>
			
			<nav style="max-width: 1200px; display: flex; align-items: center;width: 100%;">
				<ul class="menu" style = "margin: 0 auto;">
					<li><p id = "filtreHomme" style = "cursor:pointer;">Homme</p></li>
					<script>
						document.getElementById("filtreHomme").addEventListener("click", function () {
							const sections = document.querySelectorAll("section");

							sections.forEach(sec => {
								const texte = sec.textContent.toLowerCase();

								if (texte.includes("femme")) {
									sec.style.display = "none";   // on cache
								} else {
									sec.style.display = "";       // on montre
								}
							});
						});
					</script>
					
					<li><p id = "filtreFemme" style = "cursor:pointer;">Femme</p></li>
					<script>
						document.getElementById("filtreFemme").addEventListener("click", function () {
							const sections = document.querySelectorAll("section");

							sections.forEach(sec => {
								const texte = sec.textContent.toLowerCase();

								if (texte.includes("homme")) {
									sec.style.display = "none";   // on cache
								} else {
									sec.style.display = "";       // on montre
								}
							});
						});
					</script>
					
					<li><p id = "filtreMixte" style = "cursor:pointer;">Mixte</p></li>
					<script>
						document.getElementById("filtreMixte").addEventListener("click", function () {
							const sections = document.querySelectorAll("section");

							sections.forEach(sec => {
								const texte = sec.textContent.toLowerCase();

								if (texte.includes("homme") || texte.includes("femme")) {
									sec.style.display = "none";   // on cache
								} else {
									sec.style.display = "";       // on montre
								}
							});
						});
					</script>
					
					<li class="color-menu">
						<p>Couleur</p>
						<ul class="color-submenu">
							<li id="red">Rouge</li>
							<script>
								document.getElementById("red").addEventListener("click", function () {
									const sections = document.querySelectorAll("section");

									sections.forEach(sec => {
										const texte = sec.textContent.toLowerCase();

										if (!texte.includes("rouge") && !texte.includes("bordeaux") && !texte.includes("burgundy")) {
											sec.style.display = "none";   // on cache
										} else {
											sec.style.display = "";       // on montre
										}
									});
								});
							</script>
							
							<li id="white">Blanc</li>
							<script>
								document.getElementById("white").addEventListener("click", function () {
									const sections = document.querySelectorAll("section");

									sections.forEach(sec => {
										const texte = sec.textContent.toLowerCase();

										if (!texte.includes("blanc")) {
											sec.style.display = "none";   // on cache
										} else {
											sec.style.display = "";       // on montre
										}
									});
								});
							</script>
							
							<li id="grey">Gris</li>
							<script>
								document.getElementById("grey").addEventListener("click", function () {
									const sections = document.querySelectorAll("section");

									sections.forEach(sec => {
										const texte = sec.textContent.toLowerCase();

										if (!texte.includes("gris")) {
											sec.style.display = "none";   // on cache
										} else {
											sec.style.display = "";       // on montre
										}
									});
								});
							</script>
							
							<li id="black">Noir</li>
							<script>
								document.getElementById("black").addEventListener("click", function () {
									const sections = document.querySelectorAll("section");

									sections.forEach(sec => {
										const texte = sec.textContent.toLowerCase();

										if (!texte.includes("noir")) {
											sec.style.display = "none";   // on cache
										} else {
											sec.style.display = "";       // on montre
										}
									});
								});
							</script>
							
							<li id="brown">Marron</li>
							<script>
								document.getElementById("brown").addEventListener("click", function () {
									const sections = document.querySelectorAll("section");

									sections.forEach(sec => {
										const texte = sec.textContent.toLowerCase();

										if (!texte.includes("marron")) {
											sec.style.display = "none";   // on cache
										} else {
											sec.style.display = "";       // on montre
										}
									});
								});
							</script>
							
							<li id="beige">Beige</li>
							<script>
								document.getElementById("beige").addEventListener("click", function () {
									const sections = document.querySelectorAll("section");

									sections.forEach(sec => {
										const texte = sec.textContent.toLowerCase();

										if (!texte.includes("beige")) {
											sec.style.display = "none";   // on cache
										} else {
											sec.style.display = "";       // on montre
										}
									});
								});
							</script>
							
							<li id="orange">Orange</li>
							<script>
								document.getElementById("orange").addEventListener("click", function () {
									const sections = document.querySelectorAll("section");

									sections.forEach(sec => {
										const texte = sec.textContent.toLowerCase();

										if (!texte.includes("orange")) {
											sec.style.display = "none";   // on cache
										} else {
											sec.style.display = "";       // on montre
										}
									});
								});
							</script>
						</ul>
					</li>
				</ul>
			</nav>
		</nav>
	</header>
	<h1><?= $h1?></h1>