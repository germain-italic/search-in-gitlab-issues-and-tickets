# Notification de Progression - Documentation

## Vue d'ensemble

Implémentation d'une popup de notification qui affiche en temps réel la progression de la recherche, projet par projet, de façon asynchrone.

## Architecture

### Approche Choisie : Requêtes Séquentielles

Au lieu de modifier `search.php` pour qu'il soit non-bloquant (complexe avec SSE), nous avons créé un nouveau endpoint `search_project.php` qui cherche dans **un seul projet** à la fois.

**Le frontend appelle ce endpoint séquentiellement** pour chaque projet, ce qui permet :
- ✅ Affichage de progression en temps réel
- ✅ Affichage progressif des résultats (dès qu'un projet est terminé)
- ✅ Code simple et maintenable
- ✅ Pas de modification complexe du backend existant

### Flux de Fonctionnement

```
1. Utilisateur lance recherche avec 3 projets

2. Frontend :
   └─ Affiche popup "Starting search in 3 project(s)..."
   └─ Barre de progression: 0%

3. Appel Ajax 1 : search_project.php?projectId=804
   └─ Popup : "Searching Project A..."  [Spinner]
   └─ Barre : 0%
   └─ Réponse : 5 résultats
   └─ Popup : "✓ Project A: 5 result(s)"  [Check vert]
   └─ Barre : 33%
   └─ **Résultats Project A affichés immédiatement**

4. Appel Ajax 2 : search_project.php?projectId=306
   └─ Popup : "Searching Project B..."  [Spinner]
   └─ Barre : 33%
   └─ Réponse : 2 résultats
   └─ Popup : "✓ Project B: 2 result(s)"  [Check vert]
   └─ Barre : 66%
   └─ **Résultats Project B affichés immédiatement**

5. Appel Ajax 3 : search_project.php?projectId=123
   └─ Popup : "Searching Project C..."  [Spinner]
   └─ Barre : 66%
   └─ Réponse : 0 résultat
   └─ Popup : "✓ Project C: No results"  [Check vert]
   └─ Barre : 100%

6. Fin :
   └─ Popup : "Complete! Found results in 2 project(s)"
   └─ Barre : 100%
   └─ Auto-fermeture après 3 secondes
```

## Fichiers Créés/Modifiés

### 1. **src/search_project.php** (nouveau)
Endpoint qui cherche dans un seul projet.

**Paramètres** :
- `projectId` : ID du projet
- `searchString` : Terme recherché
- `searchIn` : JSON array `["issues", "wiki", "comments"]`

**Retour** :
```json
{
  "projectId": 804,
  "projectName": "My Project",
  "searchString": "test",
  "issues": [...],
  "wiki": [...],
  "comments": [...]
}
```

### 2. **index.php** (modifié)
Ajout de la popup HTML :
```html
<div id="progressNotification" class="progress-notification">
  <div class="progress-notification-header">
    <span class="progress-notification-title">...</span>
    <button class="progress-notification-close">×</button>
  </div>
  <div class="progress-notification-body">
    <div class="progress-bar-container">
      <div class="progress-bar" id="progressBar"></div>
    </div>
    <div class="progress-details" id="progressDetails"></div>
  </div>
</div>
```

### 3. **assets/css/styles.css** (modifié)
Ajout de ~140 lignes de CSS pour :
- Popup fixée en bas à droite
- Animation de slide-in
- Barre de progression
- Liste des items avec icônes colorées (spinner orange, check vert, croix rouge)
- Support du dark mode

### 4. **assets/js/app.js** (modifié)
Remplacement complet de la fonction `handleSearch()` :

**Avant** :
```javascript
fetch('src/search.php', { ... })  // Bloquant
  .then(data => renderResults(data))  // Tout d'un coup
```

**Après** :
```javascript
searchProjectsSequentially(0);  // Récursif

function searchProjectsSequentially(index) {
  updateProgress(percent, 'Searching Project A...');
  fetch('src/search_project.php', { projectId: ... })
    .then(data => {
      renderResults(allResults);  // Progressif
      searchProjectsSequentially(index + 1);  // Suivant
    });
}
```

Ajout de 4 fonctions utilitaires :
- `showProgressNotification()`
- `hideProgressNotification()`
- `updateProgress(percent, title)`
- `addProgressDetail(text, status)`
- `updateProgressDetail(index, text, status)`

## Interface Utilisateur

### Popup de Notification

**Position** : Coin inférieur droit (400px de large)

**Éléments** :
1. **Header** :
   - Icône spinner animée
   - Titre dynamique ("Searching...", "Complete!", etc.)
   - Bouton de fermeture (X)

2. **Barre de progression** :
   - Gradient orange/rouge
   - Largeur : 0% → 100%
   - Animation fluide

3. **Liste des détails** :
   - Un item par projet
   - 3 états :
     - 🔄 **Loading** (orange) : `Searching Project A...`
     - ✅ **Success** (vert) : `✓ Project A: 5 result(s)`
     - ❌ **Error** (rouge) : `✗ Project A: Error`
   - Scroll automatique vers le bas

### États de la Popup

| Moment | Titre | Barre | Détails |
|--------|-------|-------|---------|
| Début | "Starting search in 3 project(s)..." | 0% | Vide |
| Projet 1 en cours | "Searching Project A..." | 0% | "🔄 Searching Project A..." |
| Projet 1 terminé | "Searching Project A..." | 33% | "✅ Project A: 5 results" |
| Projet 2 en cours | "Searching Project B..." | 33% | "✅ Project A: 5 results<br>🔄 Searching Project B..." |
| Fin | "Complete! Found results in 2 project(s)" | 100% | Liste complète |
| +3s | (fermée) | - | - |

## Avantages

### 1. **Feedback Permanent**
L'utilisateur voit toujours quelque chose se passer :
- Barre de progression qui avance
- Nom du projet en cours
- Liste qui s'allonge

### 2. **Affichage Progressif**
Les résultats apparaissent au fur et à mesure :
- Projet A terminé → Résultats affichés
- Projet B terminé → Nouveaux résultats ajoutés
- L'utilisateur peut commencer à lire pendant que ça continue

### 3. **Gestion d'Erreurs**
Si un projet échoue :
- Marqué en rouge avec une croix
- La recherche continue sur les autres projets
- Pas de blocage total

### 4. **Performance Perçue**
Même si le temps total est identique, l'utilisateur a l'impression que c'est plus rapide car :
- Il voit les premiers résultats rapidement
- Il est informé de ce qui se passe
- Pas d'écran figé pendant 2 minutes

## Test

```bash
# Lancer le serveur
php -S localhost:8080

# Ouvrir le navigateur
http://localhost:8080

# Tester
1. Sélectionner 2-3 projets
2. Chercher un terme (ex: "test")
3. Observer la popup en bas à droite
4. Vérifier que les résultats apparaissent progressivement
```

## Code Propre

- ✅ Pas de logs console inutiles
- ✅ Pas de redondance
- ✅ Fonctions courtes et claires
- ✅ Noms de variables explicites
- ✅ Commentaires uniquement où nécessaire
- ✅ Code réutilisable (`search_project.php` peut être appelé indépendamment)

## Comparaison Avant/Après

| Aspect | Avant | Après |
|--------|-------|-------|
| **Feedback** | Aucun pendant 30-120s | Continu en temps réel |
| **Premiers résultats** | Après 30-120s | Après 5-10s |
| **En cas d'erreur** | Tout échoue | Continue sur les autres |
| **UX** | Frustrant | Fluide et informatif |
| **Code** | `search.php` bloquant | Requêtes séquentielles |

## Notes Techniques

- Les requêtes sont **séquentielles** (une après l'autre), pas parallèles
- Chaque requête à `search_project.php` peut prendre 5-30s selon le projet
- Le total peut être long MAIS l'utilisateur est informé en permanence
- La popup reste visible jusqu'à la fin (pas d'auto-hide prématuré)
- Auto-fermeture 3 secondes après la fin
- Fermeture manuelle possible à tout moment (bouton X)

## Améliorations Futures Possibles

1. **Parallélisation** : Lancer 2-3 projets en parallèle (plus complexe)
2. **Annulation** : Bouton pour arrêter la recherche en cours
3. **Persistance** : Sauvegarder les résultats en cache
4. **Optimisation** : Ne chercher les commentaires que si nécessaire

## Conclusion

L'implémentation est **simple, propre et efficace**. L'utilisateur a maintenant un feedback permanent pendant la recherche, ce qui améliore drastiquement l'expérience utilisateur, surtout pour les recherches longues (commentaires, nombreux projets).
