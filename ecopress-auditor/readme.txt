=== EcoPress Auditor & Fixer ===
Contributors: ecopress-community
Tags: green-it, performance, sustainability, eco-design, carbon-footprint
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 8.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Mesurez l'empreinte carbone de vos pages WordPress et corrigez les problèmes d'éco-conception en un clic.

== Description ==

**EcoPress Auditor & Fixer** est un plugin d'éco-conception pour WordPress qui vous aide à réduire l'empreinte carbone de votre site web.

= Fonctionnalités principales =

* **Eco-HUD** : Un indicateur léger sur le front-end (visible uniquement par les éditeurs) qui affiche le poids de la page, le score Eco-Index (A-G) et l'estimation CO2.
* **Centre d'Audit** : Une metabox dans l'éditeur qui analyse les 5 ressources les plus lourdes de chaque page.
* **Media Optimizer** : Conversion en un clic des images vers le format WebP.
* **Script Unloader** : Désactivation sélective de scripts tiers page par page.
* **DOM Cleaner** : Suppression des emojis WordPress, des oEmbeds et du versioning CSS/JS.
* **Lazy-Load Force** : Application automatique de `loading="lazy"` sur toutes les images et iframes.

= Philosophie =

Ce plugin respecte le principe de « Do no harm » :

* Aucune modification sans action explicite de l'utilisateur.
* Les images converties conservent une copie de l'original pour un retour arrière facile.
* Ultra-léger : moins de 50 Ko d'assets front-end.

= Modèle de calcul =

L'estimation CO2 repose sur le modèle de transfert de données :
CO2 (g) = Données (Go) × 0.81 kWh/Go × 490 gCO2/kWh

== Installation ==

1. Téléchargez le plugin et décompressez-le dans `/wp-content/plugins/ecopress-auditor/`.
2. Activez le plugin via le menu « Extensions » de WordPress.
3. Configurez les options dans « Réglages > EcoPress ».
4. Ouvrez une page publiée en front-end pour voir l'Eco-HUD (nécessite d'être connecté avec des droits d'édition).

== Frequently Asked Questions ==

= Quelles versions de PHP sont supportées ? =

PHP 8.2 et supérieur. Le plugin utilise les types stricts et les fonctionnalités modernes de PHP.

= Le plugin ralentit-il mon site ? =

Non. L'Eco-HUD ne s'affiche que pour les utilisateurs connectés ayant des droits d'édition. Les résultats d'audit sont mis en cache 24h via les Transients. Aucun JavaScript n'est chargé pour les visiteurs classiques.

= Le plugin modifie-t-il ma base de données ? =

Uniquement lorsque vous déclenchez explicitement une action (conversion d'image, sauvegarde de réglages). Aucune table supplémentaire n'est créée.

= Puis-je annuler une conversion d'image ? =

Oui. Le fichier original est conservé et peut être restauré via l'API du plugin.

= Le DOM Cleaner peut-il casser mon site ? =

Les optimisations (suppression emojis, oEmbeds, versioning) sont toutes optionnelles et désactivables individuellement. Si un problème survient, désactivez simplement l'option concernée.

== Screenshots ==

1. L'Eco-HUD affiché en front-end avec le score, le poids et l'empreinte CO2.
2. La metabox d'audit dans l'éditeur avec la liste des ressources les plus lourdes.
3. La page de réglages avec les options du DOM Cleaner et du Lazy-Load.

== Changelog ==

= 1.0.0 =
* Version initiale.
* Eco-HUD front-end pour les éditeurs.
* Centre d'audit avec analyse des ressources.
* Media Optimizer : conversion WebP/AVIF.
* Script Unloader : désactivation par page.
* DOM Cleaner : emojis, oEmbeds, versioning.
* Lazy-Load Force : images et iframes.
* Page de réglages globaux.

== Upgrade Notice ==

= 1.0.0 =
Première version du plugin EcoPress Auditor & Fixer.
