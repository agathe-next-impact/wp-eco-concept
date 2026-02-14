=== EcoDiag ===
Contributors: agathe-next-impact
Tags: eco-conception, performance, optimization, green-web, audit
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 1.0.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Plugin WordPress d'audit et d'optimisation écoconception. Diagnostic front-end, par contenu et global avec actions d'optimisation automatisées.

== Description ==

EcoDiag est un plugin WordPress d'audit et d'optimisation écoconception qui opère sur trois niveaux :

= Front-end connecté =
Bandeau de diagnostic résumé dans l'admin bar, visible uniquement par les administrateurs. Affiche le score EcoDiag, le poids de la page, le nombre de requêtes HTTP, la taille du DOM et les problèmes d'images.

= Admin par contenu =
Metabox de diagnostic complet avec actions d'optimisation, présente sur chaque écran d'édition (page, post, CPT). Inclut la conversion d'images en WebP/AVIF, l'ajout de lazy loading, la compression d'images, la conversion d'embeds en façades légères, et plus.

= Admin globale =
Page de réglages avec diagnostic site complet, actions d'optimisation globales et tableau de bord de suivi. Inclut le nettoyage de la base de données, la gestion des plugins/thèmes inactifs, l'optimisation de la médiathèque, le nettoyage du head HTML et le chargement conditionnel des assets.

== Features ==

* Score EcoDiag 0-100 inspiré de l'EcoIndex
* Diagnostic d'images (format, lazy load, dimensions, compression, srcset)
* Diagnostic de vidéos et embeds (iframes, autoplay)
* Diagnostic du contenu éditorial (révisions, métadonnées, shortcodes)
* Nettoyage de la base de données (révisions, transients, options orphelines)
* Gestion des plugins et thèmes inactifs
* Conversion et compression bulk de la médiathèque
* 12 toggles de nettoyage du head HTML
* Chargement conditionnel des assets par plugin
* Recommandations serveur (PHP, cache, compression, hébergeur vert)
* Historique et suivi des scores
* Export CSV des résultats
* Alertes email si le score descend sous un seuil
* Audit planifié automatique (hebdomadaire ou mensuel)

== Installation ==

1. Uploadez le dossier `ecodiag` dans `/wp-content/plugins/`
2. Activez le plugin via le menu "Plugins" de WordPress
3. Accédez au menu "EcoDiag" dans l'admin pour configurer le plugin

== Changelog ==

= 1.0.0 =
* Version initiale
* Diagnostic front-end (admin bar)
* Diagnostic par contenu (metabox)
* Diagnostic global (admin)
* Actions d'optimisation automatisées
* Réglages et chargement conditionnel
* Historique et monitoring
