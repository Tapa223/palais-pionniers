<?php
if (!isset($pdo) || !suggestions_disponibles($pdo)) {
    return;
}
$_SESSION['suggestion_affichee'] = time();
$retourSuggestion = $_GET['suggestion'] ?? '';
?>
<style>
  @media (prefers-reduced-motion: no-preference) {
    #suggestionOuvrir { animation: suggestionFlotte 3.6s ease-in-out infinite; }
    #suggestionLibelle { display:inline-block; animation: suggestionApparait .8s ease-out both, suggestionRespire 3.6s ease-in-out .8s infinite; }
    #suggestionOuvrir:hover, #suggestionOuvrir:focus-visible,
    #suggestionOuvrir:hover #suggestionLibelle, #suggestionOuvrir:focus-visible #suggestionLibelle { animation-play-state: paused; }
  }
  @keyframes suggestionFlotte {
    0%, 100% { transform: translateY(0); }
    50%      { transform: translateY(-4px); }
  }
  @keyframes suggestionApparait {
    from { opacity: 0; transform: translateX(4px); }
    to   { opacity: 1; transform: none; }
  }
  @keyframes suggestionRespire {
    0%, 100% { opacity: 1; }
    50%      { opacity: .78; }
  }
</style>
<div id="suggestion" class="fixed right-4 z-40" style="bottom:1rem">
  <a href="?suggestion=ouvrir#suggestionModal" id="suggestionOuvrir" role="button" aria-haspopup="dialog" aria-controls="suggestionModal"
     aria-label="Une suggestion ? Envoyer une suggestion anonyme"
     class="flex items-center gap-2 bg-primary text-white rounded-full shadow-xl px-4 py-3 text-xs font-black tracking-wide hover:bg-accent transition">
    <i class="fas fa-comment-dots text-base"></i>
    <span id="suggestionLibelle" aria-hidden="true">Une suggestion ?</span>
  </a>
</div>

<div id="suggestionModal" role="dialog" aria-modal="true" aria-labelledby="suggestionTitre"
     class="<?= $retourSuggestion ? '' : 'hidden' ?> fixed inset-0 z-50 bg-black/50 flex items-end sm:items-center justify-center p-4">
  <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-5 sm:p-6">
    <div class="flex items-start justify-between gap-3 mb-3">
      <div>
        <h2 id="suggestionTitre" class="font-black text-primary text-base uppercase italic">Une suggestion ?</h2>
        <p class="text-xs text-slate-500 mt-0.5">Partagez une idée ou une remarque avec l'équipe du Palais. C'est anonyme : ni nom, ni e-mail, ni compte.</p>
      </div>
      <a href="index.php" id="suggestionFermer" role="button" aria-label="Fermer"
         class="w-8 h-8 flex-shrink-0 flex items-center justify-center rounded-full bg-slate-100 text-slate-500 hover:text-accent transition">
        <i class="fas fa-times text-xs"></i>
      </a>
    </div>

    <div id="suggestionMerci" class="<?= $retourSuggestion === 'merci' ? '' : 'hidden' ?> rounded-xl bg-emerald-50 border border-emerald-200 p-4 text-sm font-bold text-emerald-700">
      <i class="fas fa-check-circle mr-1"></i>
      <span>Merci ! Votre suggestion a bien été transmise, de façon anonyme, à l'équipe du Palais.</span>
    </div>

    <form id="suggestionForm" method="POST" action="suggestion.php" class="<?= $retourSuggestion === 'merci' ? 'hidden' : '' ?> space-y-3">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      <div aria-hidden="true" style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden">
        <label>Site web <input type="text" name="site_web" tabindex="-1" autocomplete="off"></label>
      </div>
      <textarea name="contenu" rows="4" required minlength="10" maxlength="2000"
                placeholder="Votre suggestion…"
                class="w-full rounded-xl border-2 border-slate-100 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-primary outline-none focus:border-primary resize-none"></textarea>
      <p id="suggestionErreur" class="<?= $retourSuggestion === 'erreur' ? '' : 'hidden' ?> text-xs font-bold text-accent">
        Votre suggestion n'a pas pu être envoyée. Merci de réessayer dans quelques instants.
      </p>
      <div class="flex justify-end">
        <button type="submit" class="bg-accent text-white text-xs font-black uppercase px-5 py-2.5 rounded-xl hover:bg-accent-dark transition">
          Envoyer
        </button>
      </div>
    </form>
  </div>
</div>

<script>
(function () {
    const modal = document.getElementById('suggestionModal');
    const form  = document.getElementById('suggestionForm');
    const merci = document.getElementById('suggestionMerci');
    const err   = document.getElementById('suggestionErreur');
    const ouvrir = () => { modal.classList.remove('hidden'); form.querySelector('textarea')?.focus(); };
    const fermer = () => modal.classList.add('hidden');
    document.getElementById('suggestionOuvrir').addEventListener('click', e => { e.preventDefault(); ouvrir(); });
    document.getElementById('suggestionFermer').addEventListener('click', e => { e.preventDefault(); fermer(); });
    modal.addEventListener('click', e => { if (e.target === modal) fermer(); });
    document.addEventListener('keydown', e => { if (e.key === 'Escape') fermer(); });

    form.addEventListener('submit', async e => {
        e.preventDefault();
        err.classList.add('hidden');
        const bouton = form.querySelector('button[type="submit"]');
        bouton.disabled = true;
        try {
            const rep = await fetch(form.action, { method: 'POST', body: new FormData(form), headers: { 'X-Requested-With': 'fetch' } });
            const data = await rep.json();
            if (data.ok) {
                form.reset();
                form.classList.add('hidden');
                merci.querySelector('span').textContent = data.message;
                merci.classList.remove('hidden');
            } else {
                err.textContent = data.message;
                err.classList.remove('hidden');
            }
        } catch (x) {
            err.textContent = "Votre suggestion n'a pas pu être envoyée. Merci de réessayer.";
            err.classList.remove('hidden');
        } finally {
            bouton.disabled = false;
        }
    });

})();
</script>
