<?php
/***************************************************************************
*                        lang_main.php
*                        -------------------
*   begin                : Dimanche, 21 Décembre, 2008
*   copyright            : (C) Angelblade
*   email                : Angelblade@hotmail.fr
*
***************************************************************************/

// Erreur inconnue
$lang['unknow_title'] = 'Erreur inconnue';
$lang['unknow_msg'] = 'Une erreur inconnue est survenue';

//4xx Erreur client
$lang['400_title'] = 'Demande incorrecte';
$lang['400_msg'] = 'La requête envoyée n\'a pas été comprise par le serveur';
$lang['401_title'] = 'Authorisation requise';
$lang['401_msg'] = 'Une authentification est nécessaire pour accéder à la ressource';
$lang['402_title'] = 'Paiement requis';
$lang['402_msg'] = 'Un paiement est requis pour accéder à la ressource';
$lang['403_title'] = 'Accès interdit';
$lang['403_msg'] = 'Vous n\'avez pas le droit d\'accéder au répertoire demandé';
$lang['404_title'] = 'Page introuvable';
$lang['404_msg'] = 'La page que vous cherchez n\'existe pas ou plus';
$lang['405_title'] = 'Méthode interdite';
$lang['405_msg'] = 'La méthode %s n\'est pas utilisable pour l\'URL requise';
$lang['408_title'] = 'Requête trop longue';
$lang['408_msg'] = 'Temps d\'attente d\'une réponse du serveur écoulé';
$lang['410_title'] = 'Cette ressources n\'existe plus';
$lang['410_msg'] = 'L\'URL demandée n\'est plus accessible sur ce serveur et aucune adresse de redirection n\'est connue';
$lang['411_title'] = 'Longeur du contenu illégal';
$lang['411_msg'] = 'La longueur de la requête n\'a pas été précisée';
$lang['412_title'] = 'Précondition échouées';
$lang['412_msg'] = 'Préconditions envoyées par la requête non-vérifiées';
$lang['413_title'] = 'Volume de la demande trop grand';
$lang['413_msg'] = 'Le volume des données excède la limite de capacité ou la méthode n\'autorise pas de transfert de données';
$lang['414_title'] = 'L\'URI demandée est trop longue';
$lang['414_msg'] = 'L\'URL est trop longue pour ce serveur';
$lang['415_title'] = 'Type de média invalide';
$lang['415_msg'] = 'Le serveur ne supporte pas le type de média utilisé dans votre requête';

// 5xx Erreur serveur
$lang['500_title'] = 'Erreur du serveur';
$lang['500_msg'] = 'Le serveur a été victime d\'une erreur interne et n\'a pas pu faire aboutir votre requête';
$lang['501_title'] = 'Non implémenté';
$lang['501_msg'] = 'Le serveur n\'est pas en mesure d\'effectuer l\'action requise';
$lang['502_title'] = 'Erreur proxy';
$lang['502_msg'] = 'Le serveur proxy a reçu une réponse incorrecte de la part d\'un serveur supérieur';
$lang['503_title'] = 'Service inaccessible';
$lang['503_msg'] = 'Service temporairement indisponible ou en maintenance';
$lang['506_title'] = 'La variante varie elle même';
$lang['506_msg'] = 'Une variante pour l\'entité requise est elle-même une ressource changeante';
$lang['509_title'] = 'Fin de bande passante';
$lang['509_msg'] = 'Ressource utilisée par de trop nombreux serveurs pour indiquer un dépassement de quota';

?>