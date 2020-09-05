<?php

/*
 * Travail sur les dossiers et fichier -----------------------------------------------------------------------------------------------------------
 */
function createFolder(string $path): void {
	global $error;
	echo "Tentative de création du dossier" . str_replace('../', '', $path) . "...\n";

	if(@mkdir($path, 0777)){
		echo "Création du dossier " . str_replace('../', '', $path) . " terminé \n";
	}else{
		echo "Echec de la creation du dossier " . str_replace('../', '', $path) . "...\n";
		echo "Seconde tentative...\n";

		if(@mkdir($path, 0777, TRUE)){
			echo "Seconde tentative: Création du dossier " . str_replace('../', '', $path) . " terminé \n";
		}else{
			echo "Seconde tentative: Echec de la creation du dossier " . str_replace('../', '', $path) . "...\n";
			$error[] = "Le dossier " . str_replace('../', '', $path) . " n'a pas pu être créé.";
		}
	}
}

function copyFile(string $pathOrigine, string $pathCible) {
	global $error;
	echo "Tentative de copie du fichier " . str_replace('../', '', $pathOrigine) . " vers " . str_replace('../', '', $pathCible) . "...\n";

	if(@copy($pathOrigine, $pathCible)){
		echo "Copie du fichier " . str_replace('../', '', $pathOrigine) . " vers " . str_replace('../', '', $pathCible) . " terminé\n";
	}else{
		echo "Echec de la copie du fichier " . str_replace('../', '', $pathOrigine) . " vers " . str_replace('../', '', $pathCible) . "\n";
		$error[] = "Le fichier " . str_replace('../', '', $pathOrigine) . " n'a pas pu être copié vers " . str_replace('../', '', $pathCible) . "\n";
	}
}

function selectStr(string $str, string $startDel, string $endDel = NULL, int $nbrOc = 1): ?string {
	if($endDel === NULL){
		$endDel = $startDel;
	}

	$find = 1;
	$content = NULL;
	$tabstr = str_split($str);
	$pass = FALSE;
	$findEndDel = FALSE;

	for($i = 0; $i < count($tabstr); $i++){

		if(is_null($content)){
			if($tabstr[$i] === $startDel && $nbrOc === $find){
				$content = "";
			}else if($tabstr[$i] === $startDel){
				if($pass === TRUE){
					$find++;
					$pass = FALSE;
				}else{
					$pass = TRUE;
				}
			}
		}else{
			if($tabstr[$i] !== $endDel){
				$content = $content . $tabstr[$i];
			}else{
				$findEndDel = TRUE;
				break;
			}
		}
	}

	if($findEndDel === TRUE){
		return $content;
	}else{
		return NULL;
	}
}

function rmCharByStr(string $char, string $str, int $nbrOc, bool $byStart = TRUE): string {
	$tabChar = str_split($str);
	$j = 1;

	if($byStart === TRUE){
		for($i = 0; $i < count($tabChar); $i++){
			if($tabChar[$i] === $char){
				if($nbrOc === $j){
					$tabChar[$i] = "";
					break;
				}else{
					$j++;
				}
			}
		}
	}else{
		for($i = count($tabChar) - 1; $i > 0; $i--){
			if($tabChar[$i] === $char){
				if($nbrOc === $j){
					$tabChar[$i] = "";
					break;
				}else{
					$j++;
				}
			}
		}
	}

	return implode($tabChar);
}

/*
 * @Path: Chemin vers le fichier cible
 * @Data: Donnée à insérer
 * @Delime: Char || String: Le délimiteur
 */
function addAppendDelim(string $path, string $data, string $delim): bool {
	if($file = file($path)){
		for($i = count($file) - 1; $i > 0; $i--){

			if(trim($file[$i]) === $delim){
				$file[$i - 1] = $file[$i - 1] . $data;
				if(file_put_contents($path, $file)){
					return true;
					break;
				}else{
					return false;
					break;
				}
			}
		}
	}else{
		return false;
	}

	return false;
}

function controlDir(string $path, array $tab): bool {
	foreach($tab as $value){
		if($value === $path){
			return true;
		}
	}

	return false;
}

function copyDirRecursive($pathDirSrc, $pathDirDest): void {
	$tabGeneral = scandir($pathDirSrc);

	$tabDirWait = array();

	$dir = $pathDirSrc;

	$noPass = count(explode('/', $dir));

	do{
		$stop = false;

		do{
			$tabGeneral = scandir($dir);
			$dirFind = false;

			for($i = 0; $i < count($tabGeneral); $i++){
				if(is_dir($dir . $tabGeneral[$i] . '/') && $tabGeneral[$i] !== '.' && $tabGeneral[$i] !== '..'){
					if(!controlDir($dir . $tabGeneral[$i] . '/', $tabDirWait)){
						$dir = $dir . $tabGeneral[$i] . '/';
						$dirFind = true;
						break;
					}
				}
			}

			if(!$dirFind){
				$tabDirWait[] = $dir;
				$tabEx = explode('/', $dir);
				unset($tabEx[count($tabEx) - 2]);
				$dir = implode('/', $tabEx);
			}

			if(count(explode('/', $dir)) < $noPass){
				$stop = true;
				break;
			}
		}
		while($dirFind === true);
	}
	while($stop === false);

	$tabDest = explode('/', $pathDirSrc);

	foreach($tabDirWait as $valDir){

		$tabSrc = explode('/', $valDir);

		$racSrc = null;
		$findSrc = false;
		foreach($tabSrc as $valSrc){


			if($tabDest[count($tabDest) - 2] === $valSrc){
				$racSrc = $valSrc;
				$findSrc = true;
			}else if($findSrc === true){
				$racSrc = $racSrc . '/' . $valSrc;
			}
		}

		@mkdir($pathDirDest . $racSrc, 0777, true);

		$newPath = $pathDirDest . $racSrc . '/';

		$tabToCopy = scandir($valDir);

		foreach($tabToCopy as $value){
			if(!is_dir($valDir . $value) && $value !== '.' && $value !== '..'){
				if(copy($valDir . $value, $newPath . $value)){
					echo "Copie de " . $valDir . $value . " vers " . $pathDirDest . $value . "...\n";
				}else{
					echo "Echec: Le fichier " . $valDir . $value . " n'a pas pu être copié vers " . $pathDirDest . $value . "\n";
				}
			}
		}
	}
}

function active($path) {
	$file = file($path);

	for($i = 0; $i < count($file); $i++){
		$file[$i] = str_replace('/*&', '', $file[$i]);
	}

	file_put_contents($path, $file);
}

/*
 * DataBase -------------------------------------------------------------------------------------------------------------------------------------------
 */
function configDb() {
	$restart = false;
	$db = null;

	do{

		echo "Récupération de la configuration de PDOFactory...\n";
		require '../../config/config-dev.php';
		global $DataBase;
		require '../../system/PDOFactory.php';

		$pdo = new NoMess\Core\PDOFactory();

		if($db = $pdo->getConnection()){
			echo "Configuration réussie\n";
			return $db;
		}else{
			echo "Echec de la configuration\n";
			echo "Lancement de la configuration manuel\n";
		}


		echo "\e[0;1m";
		$dataBase = readline("Saisissez le nom de la base: ");
		echo "\e[0m";

		echo "\e[0;1m";
		$user = readline("Saisissez l'utilisateur: ");
		echo "\e[0m";

		echo "\e[0;1m";
		$mdp = readline("Saisissez le mot de passe: ");
		echo "\e[0m";

		try{

			$db = new PDO('mysql:host=localhost;dbname=' . $dataBase, $user, $mdp);
			$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			return $db;
		}
		catch(PDOException $e){
			echo $e->getMessage();
			echo "\nEchec de la connexion, redémarage de la configuration...\n";
			$restart = true;
		}
	}
	while($restart === true);
}

function createTable(string $value, $db): bool {
	try{
		echo "Préparation de la requête...\n";
		$req = $value;
		echo "Tentative de création de la table...\n";
		$db->exec($req);
		echo "Création de la table reussie\n";

		return true;
	}
	catch(PDOException $e){
		echo "Echec de la création de la table\nErreur: " . $e->getMessage();
		return false;
	}
}


/*
 * Méthod util ---------------------------------------------------------------------------------------------------------------------------------------------------
 */
function afficheError(array $error): void {
	echo "\n\n\n";

	$nbrError = count($error);
	if($nbrError > 0){
		echo "Des erreurs sont survenues pendant l'installation du plugin:(" . $nbrError . ")\n\n";

		foreach($error as $key => $value){
			echo $key . ": " . $value . "\n";
		}

		echo "Installation terminé\n";
	}else{
		echo "0 Erreur, Installation terminé avec succès\n";
	}
}


// ReadLine simplifié
function rdl(string $str) {
	echo "\e[0;1m";
	$response = readline($str);
	echo "\e[0m";

	if(empty($response)){
		return NULL;
	}

	return $response;
}

function arrayByValue($value, $tab, $method = null): ?string {
	foreach($tab as $key2 => $value2){

		if($method != null){
			if(trim($value2->$method()) == trim($value)){
				return $value2;
			}
		}else{
			if($value === $value2){
				return $value2;
			}
		}
	}

	return null;
}


