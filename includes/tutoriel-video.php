<?php
/*
 * Emplacement du tutoriel vidéo « Comment réserver ? ».
 * Réglages : config/tutoriel_video.php (fichier MP4 déposé ou lien YouTube).
 * Sans vidéo, un encadré d'attente présente les étapes de la réservation.
 */
$tv = require __DIR__ . '/../config/tutoriel_video.php';
$racineSite = dirname(__DIR__);
$tvFichier  = (!empty($tv['fichier']) && is_file($racineSite . '/' . $tv['fichier'])) ? $tv['fichier'] : null;
$tvAffiche  = (!empty($tv['affiche']) && is_file($racineSite . '/' . $tv['affiche'])) ? $tv['affiche'] : null;
$tvYoutube  = null;
if (!empty($tv['youtube']) && preg_match('~(?:v=|youtu\.be/|embed/|shorts/)?([A-Za-z0-9_-]{11})(?:[?&].*)?$~', trim($tv['youtube']), $m)) {
    $tvYoutube = $m[1];
}
?>
<section id="tutoriel-video" style="scroll-margin-top:6rem" aria-labelledby="titreTutoriel">
  <div class="rounded-2xl overflow-hidden border border-slate-100 bg-white shadow-sm">
    <div class="h-1.5 flex" aria-hidden="true"><span class="flex-1 bg-green-600"></span><span class="flex-1 bg-yellow-400"></span><span class="flex-1 bg-accent"></span></div>
    <div class="p-5 sm:p-8">
      <p class="text-accent font-black tracking-[0.2em] uppercase text-[10px] sm:text-xs mb-2">Tutoriel vidéo</p>
      <h2 id="titreTutoriel" class="text-xl sm:text-3xl font-black italic uppercase tracking-tighter text-primary mb-5"><?= e($tv['titre'] ?? 'Comment réserver ?') ?></h2>

      <?php if ($tvYoutube): ?>
      <div class="relative w-full overflow-hidden rounded-2xl bg-slate-900" style="padding-top:56.25%">
        <iframe class="absolute inset-0 w-full h-full" src="https://www.youtube-nocookie.com/embed/<?= e($tvYoutube) ?>" title="<?= e($tv['titre'] ?? 'Tutoriel vidéo') ?>"
                loading="lazy" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen></iframe>
      </div>
      <?php elseif ($tvFichier): ?>
      <video class="w-full rounded-2xl bg-slate-900" controls preload="metadata" playsinline <?= $tvAffiche ? 'poster="' . e($tvAffiche) . '"' : '' ?>>
        <source src="<?= e($tvFichier) ?>" type="video/mp4">
        Votre navigateur ne peut pas lire cette vidéo.
      </video>
      <?php else: ?>
      <div class="grid md:grid-cols-2 gap-5">
        <div class="rounded-2xl bg-primary text-white flex flex-col items-center justify-center text-center p-8 py-10">
          <span class="w-16 h-16 rounded-full bg-white/10 flex items-center justify-center mb-4"><i class="fas fa-play text-2xl text-accent"></i></span>
          <p class="font-black uppercase italic tracking-tight">Vidéo bientôt disponible</p>
          <p class="text-xs text-white/70 mt-2">En attendant, voici les étapes pour réserver un espace.</p>
        </div>
        <ol class="space-y-3">
          <?php foreach ([
            ['fa-user-plus', 'Créez votre compte', 'Bouton « Réserver » en haut du site : quelques informations suffisent.'],
            ['fa-building', 'Choisissez l\'espace', 'Dans « Espaces », ouvrez la fiche et cliquez sur « Réserver cet espace ».'],
            ['fa-calendar-check', 'Envoyez votre demande', 'Tarif, date, horaires, motif : l\'administration l\'examine sous 24 h.'],
            ['fa-cash-register', 'Payez au guichet', 'Dans les 48 h après validation, puis téléchargez vos documents dans « Mon compte ».'],
          ] as $n => [$ic, $titre, $txt]): ?>
          <li class="flex gap-3 items-start">
            <span class="w-9 h-9 flex-shrink-0 rounded-xl bg-slate-50 border border-slate-100 flex items-center justify-center font-black text-accent"><?= $n + 1 ?></span>
            <div>
              <p class="font-black text-primary text-sm"><i class="fas <?= $ic ?> text-slate-400 mr-1"></i><?= e($titre) ?></p>
              <p class="text-xs text-slate-600 mt-0.5"><?= e($txt) ?></p>
            </div>
          </li>
          <?php endforeach; ?>
        </ol>
      </div>
      <?php endif; ?>
    </div>
  </div>
</section>
