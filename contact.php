<?php
require_once __DIR__ . '/includes/auth.php';
$pageTitle = "Contact — Palais des Pionniers";
$page = 'contact.php';
require __DIR__ . '/includes/header.php';
?>

<!-- ============================================
     1. HEADER DE PAGE (Plus sombre pour le contraste)
     ============================================ -->
<section class="relative py-20 bg-slate-900 text-white overflow-hidden">
    <div class="absolute inset-0 opacity-20 bg-[url('assets/images/pattern.png')]"></div>
    <div class="container mx-auto px-4 relative z-10">
        <h2 class="text-accent font-black tracking-[0.3em] uppercase text-xs mb-4">Contact Institutionnel</h2>
        <h1 class="text-5xl md:text-6xl font-black italic tracking-tighter uppercase">
            À votre <span class="text-accent">Écoute</span>
        </h1>
    </div>
</section>

<section class="py-24 bg-slate-50"> <!-- Fond légèrement grisé pour faire ressortir les cartes -->
    <div class="container mx-auto px-4">
        <div class="grid lg:grid-cols-12 gap-16 items-start">
            
            <!-- ============================================
                 2. INFORMATIONS DE CONTACT & RÉSEAUX
                 ============================================ -->
            <div class="lg:col-span-5 space-y-10">
                <div>
                    <h3 class="text-4xl font-black text-primary uppercase italic tracking-tighter mb-10">Informations</h3>
                    <div class="space-y-6">
                        <?php 
                        $contacts = [
                            ['icon' => 'fa-location-dot', 'color' => 'text-blue-600', 'label' => 'Adresse', 'value' => 'Magnambougou, Bamako, Mali'],
                            ['icon' => 'fa-phone-flip', 'color' => 'text-emerald-600', 'label' => 'Téléphone', 'value' => '+223 00 00 00 00'],
                            ['icon' => 'fa-envelope-open-text', 'color' => 'text-accent', 'label' => 'Email', 'value' => 'contact@palaisdespionniers.ml'],
                            ['icon' => 'fa-clock', 'color' => 'text-slate-600', 'label' => 'Horaires', 'value' => 'Lun – Sam : 8h00 – 18h00']
                        ];
                        foreach ($contacts as $c): ?>
                            <div class="group flex items-center gap-6 p-4 rounded-2xl bg-white border border-slate-100 shadow-sm hover:shadow-md transition-all">
                                <div class="w-14 h-14 rounded-xl bg-slate-50 flex items-center justify-center text-2xl <?= $c['color'] ?> group-hover:scale-110 transition-transform">
                                    <i class="fa-solid <?= $c['icon'] ?>"></i>
                                </div>
                                <div>
                                    <p class="text-[10px] font-black uppercase tracking-widest text-slate-400 mb-0.5"><?= $c['label'] ?></p>
                                    <p class="text-slate-900 font-bold text-lg leading-tight"><?= $c['value'] ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- RÉSEAUX SOCIAUX - Look Coloré et Pro -->
                <div class="pt-10">
                    <h3 class="text-[11px] font-black text-slate-400 uppercase tracking-[0.3em] mb-8 flex items-center gap-3">
                        Suivre l'actualité <span class="h-px flex-1 bg-slate-200"></span>
                    </h3>
                    <div class="flex flex-wrap gap-4">
                        <?php 
                        $socials = [
                            ['name' => 'Facebook', 'icon' => 'fa-facebook-f', 'link' => '#', 'color' => 'bg-[#1877F2]'],
                            ['name' => 'X', 'icon' => 'fa-x-twitter', 'link' => '#', 'color' => 'bg-[#000000]'],
                            ['name' => 'LinkedIn', 'icon' => 'fa-linkedin-in', 'link' => '#', 'color' => 'bg-[#0077B5]'],
                            ['name' => 'YouTube', 'icon' => 'fa-youtube', 'link' => '#', 'color' => 'bg-[#FF0000]']
                        ];
                        foreach ($socials as $s): ?>
                            <a href="<?= $s['link'] ?>" class="flex items-center gap-3 px-5 py-3 rounded-xl <?= $s['color'] ?> text-white font-black text-xs uppercase tracking-widest transition-all hover:-translate-y-1 hover:shadow-lg active:scale-95">
                                <i class="fab <?= $s['icon'] ?> text-base"></i>
                                <span class="hidden sm:inline"><?= $s['name'] ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- ============================================
                 3. FORMULAIRE DE CONTACT
                 ============================================ -->
            <div class="lg:col-span-7">
                <div class="bg-white rounded-[3rem] p-8 md:p-14 border border-slate-200 shadow-2xl relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-primary/5 rounded-full -translate-y-1/2 translate-x-1/2"></div>
                    
                    <div class="mb-12 text-center relative z-10">
                        <h3 class="text-4xl font-black text-slate-900 uppercase italic tracking-tighter">Écrivez-nous</h3>
                        <div class="w-16 h-1.5 bg-accent mx-auto mt-3 rounded-full"></div>
                    </div>
                    
                    <form action="process-contact.php" method="POST" class="space-y-6 relative z-10">
                        <div class="grid md:grid-cols-2 gap-6">
                            <input type="text" name="nom" required placeholder="Votre nom complet" 
                                   class="w-full px-6 py-4 rounded-xl bg-slate-50 border-2 border-transparent focus:border-accent focus:bg-white focus:ring-0 transition-all font-bold text-slate-900 shadow-inner">
                            
                            <input type="email" name="email" required placeholder="Email professionnel" 
                                   class="w-full px-6 py-4 rounded-xl bg-slate-50 border-2 border-transparent focus:border-accent focus:bg-white focus:ring-0 transition-all font-bold text-slate-900 shadow-inner">
                        </div>

                        <select name="sujet" class="w-full px-6 py-4 rounded-xl bg-slate-50 border-2 border-transparent focus:border-accent focus:ring-0 transition-all font-bold text-slate-900 shadow-inner">
                            <option>Demande d'information générale</option>
                            <option>Réservation d'espaces</option>
                            <option>Partenariat institutionnel (EPST)</option>
                        </select>

                        <textarea name="message" rows="5" required placeholder="Votre message..." 
                                  class="w-full px-6 py-4 rounded-xl bg-slate-50 border-2 border-transparent focus:border-accent focus:bg-white focus:ring-0 transition-all font-bold text-slate-900 shadow-inner"></textarea>

                        <button type="submit" class="w-full bg-primary text-white py-6 rounded-2xl font-black uppercase tracking-[0.2em] shadow-xl hover:bg-slate-900 transition-all active:scale-95 group flex items-center justify-center">
                            Envoyer le message 
                            <div class="ml-4 w-8 h-8 rounded-lg bg-white/10 flex items-center justify-center group-hover:bg-accent transition-colors">
                                <i class="fa-solid fa-paper-plane text-sm group-hover:rotate-12 transition-transform"></i>
                            </div>
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</section>