-- =====================================================================
-- Palais des Pionniers — Biographies des personnalités
-- Source : document Word « Personnalités du Mali — Biographies professionnelles »
-- Texte repris à l'identique (un paragraphe par ligne).
--
-- 1) Personnalité déjà présente : seul le champ `parcours` est mis à jour
--    (nom, prénom, titre, espace, ordre et PHOTO ne sont pas modifiés).
-- 2) Personnalité absente : elle est créée avec nom, prénom, parcours et
--    l'espace portant son nom ; `titre` et `photo` restent vides (NULL).
-- Recherche par nom insensible aux accents et à la casse (utf8mb4_general_ci).
-- Script ré-exécutable sans créer de doublon.
-- =====================================================================

SET NAMES utf8mb4;

START TRANSACTION;

-- ---------------------------------------------------------------------
-- Daba Modibo KEÏTA
-- ---------------------------------------------------------------------
SET @bio = 'Domaine : Sport (Taekwondo)
Date de naissance : 5 avril 1981 à Abidjan (Côte d''Ivoire)
Profession : Taekwondoïste de haut niveau, ancien champion du monde, dirigeant sportif
Statut : Vice-président de la Fédération malienne de Taekwondo ; figure historique du sport malien et africain ; fondateur d''une association de promotion de la jeunesse
Biographie
Né le 5 avril 1981 à Abidjan, en Côte d''Ivoire, de parents maliens, Daba Modibo Keïta porte un prénom qui n''est pas anodin : il est nommé en hommage à Modibo Keïta, premier président de la République du Mali. Sa famille et lui sont contraints de fuir la Côte d''Ivoire en 2000, au moment de la vague de xénophobie qui précède la première guerre civile ivoirienne, et s''installent alors au Mali, où le jeune homme découvre véritablement le taekwondo. Ses débuts sur la scène compétitive remontent toutefois à 1996-1997, à l''occasion des championnats ouest-africains d''Abidjan puis de Bamako, où il décroche des médailles d''argent. Il devient ensuite médaillé de bronze au championnat ouest-africain d''Accra, avant de s''imposer comme champion du Mali en 2002 puis en 2004, confirmant sa progression par plusieurs titres remportés dans des tournois internationaux Open, à Paris, Nantes et en Picardie.
Les débuts difficiles de sa carrière internationale sont marqués par un manque criant de moyens : faute de financement suffisant pour s''entraîner, il bénéficie d''une bourse de solidarité olympique du Comité international olympique (CIO), qui lui permet de s''entraîner aux États-Unis auprès du combattant ivoirien Patrice Rémarck, puis sous la direction du technicien Jorge F. Ramos. Lors des Championnats du monde de 2007 à Pékin, faute de moyens suffisants, il doit encore loger chez des amis et s''entraîner dans la cour d''un hôtel. C''est pourtant dans ces conditions précaires qu''il réalise l''exploit qui va faire basculer sa carrière : avec son gabarit impressionnant (2,05 m pour environ 105 kg), il devient le premier Africain sacré champion du monde de taekwondo, en remportant la médaille d''or de la catégorie des plus de 84 kg face à l''Iranien Rostami Morteza. Il confirme cet exploit deux ans plus tard, à Copenhague en 2009, en conservant son titre dans la catégorie des plus de 87 kg face au Sud-Coréen Yun-Bae Nam — devenant ainsi le premier et, à ce jour, le seul Africain double champion du monde des poids lourds en taekwondo.
Surnommé « le Gladiateur », Daba Modibo Keïta porte également à deux reprises les couleurs du Mali aux Jeux Olympiques : à Pékin en 2008, où il est le porte-drapeau de la délégation malienne, puis à Londres en 2012, où il s''incline aux portes du podium malgré plusieurs blessures qui ont émaillé sa préparation. Géré par son frère aîné Badra, il a vécu et s''est entraîné tour à tour en France et aux États-Unis ; le taekwondo est resté une histoire de famille, puisque deux de ses frères et deux de ses cinq sœurs pratiquent également la discipline, ses sœurs ayant atteint le niveau de ceinture bleue.
Membre du collectif des « Champions de la Paix » de l''organisation internationale Peace and Sport, qui rassemble plus d''une centaine de sportifs de haut niveau engagés personnellement en faveur de la paix par le sport, il s''est également illustré par un engagement fort pour la jeunesse malienne : il fonde en 2008 l''Association Daba Modibo Keïta (ADMK) à Bamako, structure dédiée à la promotion des valeurs civiques, de l''éducation et de la pratique sportive chez les jeunes. Devenu une figure de référence du sport malien, il occupe aujourd''hui la fonction de vice-président de la Fédération malienne de Taekwondo, où il continue de transmettre son expérience aux jeunes générations de combattants, tout en intervenant régulièrement, y compris à l''étranger, pour la formation de sportifs d''autres pays de la sous-région. Interrogé sur son parcours, il aime rappeler que son sacre mondial « dépasse le Mali » et « appartient à toute l''Afrique ».';

UPDATE personnalites SET parcours = @bio WHERE CONCAT_WS(' ', prenom, nom) LIKE '%Daba%' AND CONCAT_WS(' ', prenom, nom) LIKE '%Keita%';

INSERT INTO personnalites (nom, prenom, titre, photo, parcours, espace_id, ordre)
SELECT 'KEÏTA', 'Daba Modibo', NULL, NULL, @bio,
       (SELECT id FROM espaces WHERE nom LIKE '%Taekwondo%' AND nom LIKE '%Daba%' ORDER BY id LIMIT 1),
       (SELECT COALESCE(MAX(p.ordre), -1) + 1 FROM personnalites p)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM personnalites WHERE CONCAT_WS(' ', prenom, nom) LIKE '%Daba%' AND CONCAT_WS(' ', prenom, nom) LIKE '%Keita%');

-- ---------------------------------------------------------------------
-- Assétou Founè SAMAKÉ MIGAN
-- ---------------------------------------------------------------------
SET @bio = 'Domaine : Sciences (physiologie végétale, génétique), Recherche et Politique
Date de naissance : 1960 à San (région de Ségou), Mali
Formations académiques : Doctorat en sciences biologiques, spécialité génétique des plantes et amélioration variétale, Université d''État de Kharkiv (ex-URSS) ; certificat en biotechnologie agricole, Laboratoire de biotechnologie UNESCO de l''Université Cheikh-Anta-Diop de Dakar
Profession : Professeure titulaire de biologie, physiologiste, enseignante-chercheuse
Statut actuel : Conseillère spéciale du Président de la Transition, Chef de l''État, le Général d''Armée Assimi Goïta (nommée par décret présidentiel du 11 août 2023) ; présidente du Conseil d''administration de RobotsMali ; ancienne ministre de l''Enseignement supérieur et de la Recherche scientifique (2016-2019)
Biographie
Née en 1960 à San, dans la région de Ségou, Assétou Founè Samaké Migan effectue ses études supérieures en ex-URSS, à l''Université d''État de Kharkiv, où elle obtient un doctorat en sciences biologiques spécialisé en génétique des plantes et amélioration variétale. Elle complète sa formation par un certificat en biotechnologie agricole au Laboratoire de biotechnologie UNESCO de l''Université Cheikh-Anta-Diop de Dakar, au Sénégal.
De retour au Mali, elle mène une riche carrière d''enseignante-chercheuse : de 1993 à 2000, elle enseigne la physiologie végétale à l''École Normale Supérieure (ENSup) de Bamako, où elle encadre également des mémoires et des thèses. À partir de 1997, elle devient enseignante-chercheuse puis maître de conférences à la Faculté des sciences et techniques de l''université des sciences, des techniques et des technologies de Bamako (USTTB), fonction qu''elle occupe jusqu''à son entrée au gouvernement.
Parallèlement à ses activités académiques, elle met très tôt son expertise au service du développement rural et de la sécurité alimentaire, en collaborant avec de nombreuses organisations nationales et internationales : assistante des programmes à Winrock International (2000-2004), responsable de la méthodologie au Forum social polycentrique de Bamako (2005-2006), cofondatrice et coordinatrice scientifique de l''Institut de recherche et de promotion des alternatives en développement (IRPAD, 2006-2009), puis fondatrice et responsable de programme à l''Institut africain de l''alimentation et du développement durable (2009-2011). Elle représente également l''Unitarian Service Committee of Canada au Mali, au Sénégal et au Burkina Faso de mai 2011 à juillet 2013, avant de devenir, de novembre 2013 à juin 2014, conseillère technique au ministère de la Réconciliation nationale et du Développement des régions du Nord, dans le contexte de sortie de la crise politico-sécuritaire de 2012-2013. Elle est aussi membre de plusieurs associations et ONG, dont le Forum pour un autre Mali, et secrétaire générale du Centre d''études et de réflexion au Mali (CERM).
Repérée pour sa rigueur scientifique, elle est nommée conseillère technique au ministère de l''Enseignement supérieur et de la Recherche scientifique, poste depuis lequel elle est chargée, le 28 décembre 2015, de présenter la leçon inaugurale de la rentrée universitaire devant le président Ibrahim Boubacar Keïta, sur le thème « la recherche scientifique : moteur du développement ». Sa prestation impressionne le chef de l''État, qui décide de scinder le ministère : le tout nouveau ministère de la Recherche scientifique est créé le 15 janvier 2016 et confié à Assétou Founè Samaké Migan. Sept mois plus tard, le 7 juin 2016, les deux portefeuilles sont réunis et elle devient ministre de l''Enseignement supérieur et de la Recherche scientifique, fonction qu''elle exerce jusqu''en avril 2019. Durant son mandat, elle œuvre notamment à l''opérationnalisation du Fonds compétitif pour la recherche et l''innovation technologique (FCRIT), institué en 2011 sous le président Amadou Toumani Touré mais resté inactif jusque-là, dans un contexte national où près de 70 % des financements de la recherche scientifique proviennent alors de bailleurs extérieurs. Elle supervise également, durant cette période, plusieurs sessions d''examens de fin d''année scolaire réputées pour leur bon déroulement, sans fuite de sujets.
Après son départ du gouvernement en 2019, elle poursuit son engagement au service de l''État : par décret présidentiel signé le 11 août 2023, elle est nommée Conseillère spéciale à la Présidence de la République par le Colonel (alors) Assimi Goïta, Président de la Transition, Chef de l''État, aux côtés d''autres personnalités telles que l''artiste Salif Keïta et d''anciennes ministres comme Diéminatou Sangaré et Sidibé Dedeou Ousmane. Elle occupe aujourd''hui, en cette qualité de Conseillère spéciale du Président de la Transition, Chef de l''État — depuis promu Général d''Armée en octobre 2024, et dont le mandat a été consolidé par la Charte de la Transition révisée, promulguée le 10 juillet 2025, qui lui octroie un mandat de cinq ans renouvelable sans élection —, une fonction consultative de premier plan auprès de la présidence malienne.
Son expertise scientifique continue par ailleurs d''être sollicitée en dehors de ses fonctions présidentielles : elle préside, depuis au moins début 2025, le Conseil d''administration de RobotsMali, centre dédié à la formation et à l''innovation en robotique et en intelligence artificielle, et intervient encore régulièrement comme conférencière lors de rencontres scientifiques nationales, à l''image des Journées scientifiques de biologie de l''USTTB, où elle est présentée comme « ancienne ministre de l''Enseignement supérieur et de la Recherche scientifique du Mali » et « Conseillère spéciale du Président ».';

UPDATE personnalites SET parcours = @bio WHERE CONCAT_WS(' ', prenom, nom) LIKE '%Samake%' AND CONCAT_WS(' ', prenom, nom) LIKE '%Migan%';

INSERT INTO personnalites (nom, prenom, titre, photo, parcours, espace_id, ordre)
SELECT 'SAMAKÉ MIGAN', 'Assétou Founè', NULL, NULL, @bio,
       (SELECT id FROM espaces WHERE nom LIKE '%Samake%Migan%' ORDER BY id LIMIT 1),
       (SELECT COALESCE(MAX(p.ordre), -1) + 1 FROM personnalites p)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM personnalites WHERE CONCAT_WS(' ', prenom, nom) LIKE '%Samake%' AND CONCAT_WS(' ', prenom, nom) LIKE '%Migan%');

-- ---------------------------------------------------------------------
-- Tidiane COULIBALY
-- ---------------------------------------------------------------------
SET @bio = 'Domaine : Éducation citoyenne, civisme et mouvements de jeunesse
Statut : Commissaire général de l''Association des Pionniers du Mali (APM), cadre émérite, également connu sous le surnom de « Necker »
Biographie
Tidiane Coulibaly, dit « Necker », est aujourd''hui le Commissaire général de l''Association des Pionniers du Mali (APM), héritière du Mouvement des Jeunes Pionniers créé au tout début des années 1960 sous l''impulsion du parti de Modibo Keïta, l''Union soudanaise - Rassemblement démocratique africain (US-RDA), et voué depuis lors à la formation civique et patriotique des jeunes Maliens. À la tête de cette structure reconnue d''utilité publique, il en est aujourd''hui l''un des principaux porte-voix, s''attachant à faire vivre, plus de soixante ans après sa création, l''héritage de ce mouvement de jeunesse emblématique de l''histoire postindépendance du Mali.
Homme de terrain et pédagogue engagé, il consacre son action à la refondation de la citoyenneté à travers tout le pays et s''illustre par ses prises de parole régulières lors des grands rassemblements et cérémonies nationales. Il explique volontiers la symbolique de l''emblème du mouvement — le foulard du pionnier, dont les trois pans représentent la famille, l''école et la rue, les trois milieux qui façonnent le citoyen, et dont le rouge et l''or rappellent respectivement le sang et la richesse du Mali. Il a ainsi plaidé, lors d''une visite du ministre de la Refondation de l''État en 2021, pour le retour de l''éducation pionnière au sein du système scolaire national, estimant qu''« il faut former l''homme malien pour devenir un bon citoyen et un bon patriote » avant d''être un savant.
Il s''est également illustré par des prises de position engagées en matière de civisme environnemental, notamment lors de la Journée mondiale de l''environnement, où il a appelé les Maliens à un changement profond de comportement individuel et collectif face à la dégradation de l''environnement. Figure respectée du monde associatif, il a rendu à plusieurs reprises hommage à d''anciennes figures historiques du mouvement pionnier, comme lors de l''exposition consacrée en 2017 à Bakary Koniba Traoré, dit « Bakary Pionnier », qu''il a salué comme « une légende et une fierté maliennes ».
Sous son impulsion, l''Association des Pionniers du Mali a organisé, en septembre 2023 au Palais des Pionniers de Dianéguéla, le lancement du camp national des pionniers baptisé « Camp Assimi Goïta » — le premier grand camp de formation national de l''association depuis le Camp national Maïmouna Ba de 2002, soit plus de vingt ans auparavant. À cette occasion, il a souligné les difficultés persistantes de financement de l''association et plaidé pour le déblocage effectif de la subvention prévue par le décret de reconnaissance d''utilité publique de l''APM. Son action s''inscrit pleinement dans la dynamique de refondation de la citoyenneté portée par les autorités de la Transition malienne, avec pour objectif de structurer durablement les espaces d''encadrement de la jeunesse, de réintroduire l''éducation civique dans le système scolaire et de prémunir les jeunes générations contre la délinquance.';

UPDATE personnalites SET parcours = @bio WHERE CONCAT_WS(' ', prenom, nom) LIKE '%Tidian%' AND CONCAT_WS(' ', prenom, nom) LIKE '%Coulibaly%';

INSERT INTO personnalites (nom, prenom, titre, photo, parcours, espace_id, ordre)
SELECT 'COULIBALY', 'Tidiane', NULL, NULL, @bio,
       (SELECT id FROM espaces WHERE nom LIKE '%Tidian%Coulibaly%' ORDER BY id LIMIT 1),
       (SELECT COALESCE(MAX(p.ordre), -1) + 1 FROM personnalites p)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM personnalites WHERE CONCAT_WS(' ', prenom, nom) LIKE '%Tidian%' AND CONCAT_WS(' ', prenom, nom) LIKE '%Coulibaly%');

-- ---------------------------------------------------------------------
-- Sory Ibrahim KOÏTA, dit « Chef Bomba »
-- ---------------------------------------------------------------------
SET @bio = 'Domaine : Mouvements de jeunesse, civisme et culture
Statut : Figure historique de l''Association des Pionniers du Mali (APM)
Biographie
Sory Ibrahim Koïta, connu sous le surnom affectueux de « Chef Bomba », est l''une des figures marquantes de l''histoire du Mouvement Pionnier au Mali. Il a consacré une grande partie de sa vie à l''encadrement, à l''éducation et à la formation civique de la jeunesse malienne, au sein de l''Association des Pionniers du Mali (APM), héritière du Mouvement des Jeunes Pionniers créé au tout début des années 1960 par le parti unique de Modibo Keïta, l''Union soudanaise - Rassemblement démocratique africain (US-RDA), au lendemain de l''indépendance du Mali.
À travers son action d''encadrement de terrain, il a contribué à transmettre à plusieurs générations de jeunes Maliens les valeurs fondatrices du mouvement pionnier : patriotisme, solidarité nationale, rigueur et dévouement à la patrie, incarnées notamment par le foulard tricolore porté par chaque pionnier. Son engagement s''inscrit dans la longue histoire sociale et culturelle du Mali postindépendance, marquée par la volonté de structurer l''encadrement de la jeunesse autour de valeurs civiques fortes, dans la continuité d''autres figures historiques du mouvement, à l''image de Bakary Koniba Traoré, dit « Bakary Pionnier ».
Le Mali a rendu un hommage solennel à son héritage éducatif à l''occasion de l''inauguration, le 10 mars 2026, du nouveau Palais des Pionniers de Bamako, à Dianéguéla (Magnambougou, Commune VI) — un vaste complexe éducatif et événementiel de trois hectares comprenant salles de conférences, de spectacles et de réunions, espaces informatiques, résidence, centre d''accueil et installations sportives, inauguré par le Premier ministre le Général de Division Abdoulaye Maïga, au nom du Président de la Transition, le Général d''Armée Assimi Goïta. Les différents espaces de ce complexe portent les noms de personnalités s''étant distinguées par leur engagement civique, social ou sportif, en reconnaissance de leur contribution durable à la construction citoyenne de la jeunesse malienne — une manière pour les autorités de perpétuer, à travers l''architecture même du lieu, la mémoire des artisans historiques du mouvement pionnier comme Sory Ibrahim Koïta.
Les informations biographiques publiques disponibles sur les dates précises de naissance et le parcours détaillé de Sory Ibrahim Koïta demeurent limitées ; elles pourront être complétées si des sources supplémentaires, notamment issues des archives de l''Association des Pionniers du Mali, deviennent accessibles.';

UPDATE personnalites SET parcours = @bio WHERE CONCAT_WS(' ', prenom, nom) LIKE '%Sory%' AND CONCAT_WS(' ', prenom, nom) LIKE '%Koita%';

INSERT INTO personnalites (nom, prenom, titre, photo, parcours, espace_id, ordre)
SELECT 'KOÏTA', 'Sory Ibrahim, dit « Chef Bomba »', NULL, NULL, @bio,
       (SELECT id FROM espaces WHERE nom LIKE '%Sory%Koita%' ORDER BY id LIMIT 1),
       (SELECT COALESCE(MAX(p.ordre), -1) + 1 FROM personnalites p)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM personnalites WHERE CONCAT_WS(' ', prenom, nom) LIKE '%Sory%' AND CONCAT_WS(' ', prenom, nom) LIKE '%Koita%');

-- ---------------------------------------------------------------------
-- Abdoulaye Ely DIALLO
-- ---------------------------------------------------------------------
SET @bio = 'Domaine : Secteur privé, immobilier et économie
Profession / Statut : Opérateur économique et promoteur immobilier ; cadre du mouvement Scouts et Guides du Mali
Biographie
Abdoulaye Ely Diallo est un acteur du secteur privé malien, actif dans le développement immobilier et urbain à Bamako. Il s''inscrit dans cette génération d''entrepreneurs locaux qui, portés par la croissance démographique rapide de la capitale malienne et la demande grandissante en logements modernes, investissent massivement dans la construction résidentielle pour accompagner l''expansion urbaine de Bamako.
Il est notamment connu comme le promoteur et le fondateur de la Résidence Abdoulaye Ely Diallo, un ensemble résidentiel qui porte son nom et qui est situé sur la Corniche de Magnambougou, un quartier de la Commune VI de Bamako en bordure du fleuve Niger. Ce secteur de la Corniche de Magnambougou connaît, ces dernières années, un développement immobilier soutenu, porté par plusieurs opérateurs privés qui y construisent appartements, duplex et villas de standing. Par cette activité de promotion immobilière, Abdoulaye Ely Diallo participe à la création d''emplois locaux, dans les métiers du bâtiment comme dans la gestion locative, et contribue au dynamisme du secteur privé de la construction au Mali ainsi qu''à la transformation du paysage urbain le long du fleuve Niger.
Par ailleurs, Abdoulaye Ely Diallo est également connu comme cadre du mouvement Scouts et Guides du Mali, organisation de jeunesse à laquelle il apporte son soutien, illustrant un engagement qui dépasse le seul cadre économique pour toucher à l''encadrement citoyen et éducatif des jeunes.
Les données publiques disponibles concernant sa date de naissance, son parcours de formation précis et l''ensemble de ses activités professionnelles restent limitées ; cette biographie reflète les éléments vérifiables à ce jour et pourra être enrichie si des informations complémentaires deviennent disponibles.';

UPDATE personnalites SET parcours = @bio WHERE CONCAT_WS(' ', prenom, nom) LIKE '%Ely%' AND CONCAT_WS(' ', prenom, nom) LIKE '%Diallo%';

INSERT INTO personnalites (nom, prenom, titre, photo, parcours, espace_id, ordre)
SELECT 'DIALLO', 'Abdoulaye Ely', NULL, NULL, @bio,
       (SELECT id FROM espaces WHERE nom LIKE '%Ely%Diallo%' ORDER BY id LIMIT 1),
       (SELECT COALESCE(MAX(p.ordre), -1) + 1 FROM personnalites p)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM personnalites WHERE CONCAT_WS(' ', prenom, nom) LIKE '%Ely%' AND CONCAT_WS(' ', prenom, nom) LIKE '%Diallo%');

-- ---------------------------------------------------------------------
-- Oumou DIARRA, dite « Djèma »
-- ---------------------------------------------------------------------
SET @bio = 'Domaine : Médias, journalisme et communication
Naissance : 1965 à Ségou, Mali
Dates : Décédée le 26 décembre 2023 à l''Hôpital du Mali, Bamako, à l''âge de 58 ans
Formations académiques / Profession : Institut National des Arts (INA) ; diplômée de l''École Centrale pour l''Industrie, le Commerce et l''Administration (ECICA), section Douanes ; animatrice de radio et de télévision, productrice, comédienne
Biographie
Née en 1965 à Ségou, Oumou Diarra, universellement connue sous le pseudonyme de « Djèma » (également orthographié « Dièman » ou « Dièma »), a été l''une des voix les plus populaires et les plus aimées de la radiodiffusion malienne. Après avoir fréquenté le lycée Sankoré de Bamako, sa scolarité est interrompue par une longue maladie qui la cloue neuf mois à l''hôpital du Point G, en classe de 11e année, puis l''oblige à une année entière de convalescence. Ayant pris beaucoup de retard sur ses camarades de promotion, elle se réoriente vers l''Institut National des Arts (INA), avant d''obtenir un diplôme de l''École Centrale pour l''Industrie, le Commerce et l''Administration (ECICA), section Douanes. En attendant le concours des douanes, elle fait de petits métiers — vente de marchandises rapportées du Nigeria et de Côte d''Ivoire, travail en usine de cartons, coiffure et maquillage de mariées.
C''est par un pur hasard, vers 1993, que sa carrière radiophonique commence : appelée par Michel Sangaré, animateur à la Radio Kayira, pour venir chercher un parent, elle est installée devant un micro et testée sur place. Sa voix — naturelle, grave et imposante — séduit immédiatement Oumar Mariko, le promoteur de la radio, qui lui propose d''animer l''émission nocturne « Ginguin Grin », diffusée à partir de minuit, en attendant le concours des douanes. Ses parents ignorent longtemps sa nouvelle activité ; sa mère, en particulier, se montre d''abord réticente à ce choix de carrière. Elle poursuit ensuite son parcours à la radio Tabalé, avant de rejoindre, au début des années 2000, la Chaîne 2 de l''Office de Radiodiffusion Télévision du Mali (ORTM), où elle devient, durant près de trois décennies, l''une des animatrices vedettes les plus populaires du pays.
Grâce à son ton chaleureux, sa proximité avec les réalités locales et son sens aigu de l''écoute, ses émissions consacrées à la vie quotidienne, à la solidarité, à la condition féminine et aux conseils familiaux étaient suivies par des milliers d''auditeurs à travers le pays. Elle acquiert une notoriété particulière avec « Guakounda », un théâtre radiophonique consacré à la famille qu''elle anime aux côtés de sa complice Lala Drado, dite « Fiman » — un duo si populaire que l''émission est couramment surnommée « Fiman ni Dièman ». Son émission phare, « 20 sur 20 », consacrée aux problèmes de couple et de vie familiale, rassemblait chaque soir des milliers de femmes à l''écoute. Elle anime également « Yélen » (« la lumière »), en langue bamanan, aux côtés de l''animateur Bouréma Kané. Comédienne à l''occasion, elle est également reconnue comme l''interprète du tout premier « woman show » au Mali, et se plaisait à raconter qu''elle comptait pas moins de 25 homonymes à travers le pays, sans compter ceux de sa propre famille.
Affaiblie par une longue maladie depuis son hospitalisation de juin 2016 — qui avait alors suscité de nombreuses rumeurs infondées sur son décès — elle continue néanmoins d''animer ses émissions avec passion et professionnalisme malgré une santé fragile, recevant au passage la visite de nombreuses personnalités, dont l''ancien Premier ministre Modibo Sidibé. Son décès, survenu le 26 décembre 2023 à l''Hôpital du Mali de Bamako, a suscité une vive émotion et un deuil profond dans le monde des médias et de la culture au Mali — endeuillé, la même semaine, par la disparition de deux autres figures publiques maliennes. De nombreuses personnalités et une multitude d''auditeurs anonymes lui ont rendu hommage, saluant en elle une « conseillère hors pair » et une « grande voix de la radio » qui a marqué durablement le paysage médiatique malien.';

UPDATE personnalites SET parcours = @bio WHERE CONCAT_WS(' ', prenom, nom) LIKE '%Oumou%' AND CONCAT_WS(' ', prenom, nom) LIKE '%Diarra%';

INSERT INTO personnalites (nom, prenom, titre, photo, parcours, espace_id, ordre)
SELECT 'DIARRA', 'Oumou, dite « Djèma »', NULL, NULL, @bio,
       (SELECT id FROM espaces WHERE nom LIKE '%Oumou%Diarra%' ORDER BY id LIMIT 1),
       (SELECT COALESCE(MAX(p.ordre), -1) + 1 FROM personnalites p)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM personnalites WHERE CONCAT_WS(' ', prenom, nom) LIKE '%Oumou%' AND CONCAT_WS(' ', prenom, nom) LIKE '%Diarra%');

-- ---------------------------------------------------------------------
-- Seydou Badian KOUYATÉ
-- ---------------------------------------------------------------------
SET @bio = 'Domaine : Littérature, politique et médecine
Dates : 10 avril 1928 (Bamako) – 28 décembre 2018 (Bamako), à l''âge de 90 ans
Formations académiques : Études de médecine à l''Université de Montpellier (France) ; auteur d''une thèse sur les traitements traditionnels africains de la fièvre jaune
Profession / Statut : Médecin de circonscription, homme d''État (ministre), écrivain, poète et militant politique du parti US-RDA / UM-RDA
Biographie
Né le 10 avril 1928 à Bamako, alors capitale du Soudan français, Seydou Badian Kouyaté figure parmi les plus grandes figures intellectuelles et politiques de l''histoire du Mali indépendant. Poète talentueux dès sa jeunesse, il part étudier la médecine à l''Université de Montpellier, en France, où il rédige une thèse consacrée aux traitements traditionnels africains de la fièvre jaune. Il rentre au Mali en 1956 et est nommé médecin de circonscription, avant de s''engager résolument, aux côtés de Modibo Keïta, dans la lutte pour l''indépendance du pays. Nationaliste convaincu et panafricaniste, proche du premier président du Mali, il est l''auteur des paroles de l''hymne national malien, « Pour l''Afrique et pour toi, Mali », ainsi que de l''hymne du mouvement des pionniers.
Dès l''indépendance du pays en 1960, il est nommé ministre de l''Économie rurale et du Plan. Lors du remaniement ministériel du 17 septembre 1962, il devient ministre du Développement, chargé de la Coordination économique et financière et du Plan, contribuant activement à la définition des grandes orientations économiques et sociales du jeune État malien, notamment dans le cadre du premier plan quinquennal (1961-1965). Militant convaincu du parti unique comme instrument de construction nationale dans l''Afrique post-coloniale, il devient l''un des idéologues marquants de l''Union soudanaise - Rassemblement démocratique africain (US-RDA), le parti de Modibo Keïta. Le coup d''État militaire du 19 novembre 1968, mené par le lieutenant Moussa Traoré et qui renverse Modibo Keïta, marque un tournant douloureux dans son parcours : il est déporté et détenu à la prison de Kidal, dans le Nord-Est du Mali, avant de s''exiler durant de nombreuses années à Dakar, au Sénégal, où il est accueilli sur l''invitation du président-poète Léopold Sédar Senghor. Il y passera une partie importante de sa vie avant de regagner définitivement Bamako.
De retour dans son pays, il reste une figure politique respectée et écoutée : en 1997, il se présente à l''élection présidentielle, avant de retirer sa candidature, comme la plupart des autres opposants au président sortant Alpha Oumar Konaré, pour protester contre la mauvaise organisation du scrutin. Il est par ailleurs radié puis réintégré au sein de l''US-RDA (devenue UM-RDA), dont il demeure l''une des figures morales jusqu''à la fin de sa vie, fréquemment consulté sur les grands dossiers et défis de la République malienne.
Sur le plan littéraire, Seydou Badian Kouyaté laisse une œuvre majeure et durable dans le paysage des lettres africaines, publiée entre 1957 et 2007. Son roman le plus célèbre, Sous l''orage (suivi de La Mort de Chaka), publié dès 1957, avant même l''indépendance du Mali, aborde le conflit des générations et le choc entre traditions africaines et modernité coloniale ; il reste, aujourd''hui encore, inscrit aux programmes scolaires de nombreux pays francophones et continue de bercer des générations d''écoliers africains. Il est également l''auteur d''essais et de récits marquants, parmi lesquels Les dirigeants africains face à leur peuple (1964-1965), qui lui vaut le prestigieux Grand Prix littéraire d''Afrique noire en 1965, ainsi que Le sang des masques (1976) et Noces sacrées (1977). En octobre 2007, il publie encore un roman, La Saison des pièges. En 2009, il choisit de changer officiellement de nom pour devenir Seydou Badian Noumboïna, du nom d''un village du cercle de Macina. En 2017, l''ensemble de sa production bibliographique est couronné par le Grand Prix des Mécènes, décerné lors des Grands Prix des associations littéraires (GPAL).
Seydou Badian Kouyaté s''éteint à Bamako dans la nuit du 28 au 29 décembre 2018, à l''âge de 90 ans. Sa disparition suscite un deuil national : des funérailles officielles se tiennent le 3 janvier 2019 sur le boulevard de l''Indépendance à Bamako, en présence de nombreuses personnalités maliennes, de délégations étrangères venues du Congo-Brazzaville et du Sénégal, ainsi que du corps diplomatique accrédité — témoignage de la dimension panafricaniste de l''homme, dont l''héritage politique et littéraire continue, aujourd''hui encore, d''inspirer les générations maliennes.';

UPDATE personnalites SET parcours = @bio WHERE CONCAT_WS(' ', prenom, nom) LIKE '%Seydou%' AND CONCAT_WS(' ', prenom, nom) LIKE '%Badian%';

INSERT INTO personnalites (nom, prenom, titre, photo, parcours, espace_id, ordre)
SELECT 'KOUYATÉ', 'Seydou Badian', NULL, NULL, @bio,
       (SELECT id FROM espaces WHERE nom LIKE '%Seydou%Badian%' ORDER BY id LIMIT 1),
       (SELECT COALESCE(MAX(p.ordre), -1) + 1 FROM personnalites p)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM personnalites WHERE CONCAT_WS(' ', prenom, nom) LIKE '%Seydou%' AND CONCAT_WS(' ', prenom, nom) LIKE '%Badian%');

-- ---------------------------------------------------------------------
-- Adama Samassékou, une vie au service du Mali, de l’éducation et de la pensée africaine
-- ---------------------------------------------------------------------
SET @bio = 'Adama Samassékou, une vie au service du Mali, de l’éducation et de la pensée africaine
BAMAKO — Homme politique, intellectuel, linguiste et défenseur infatigable des langues africaines, Adama Samassékou aura marqué la vie publique malienne bien au-delà des fonctions ministérielles qu’il a occupées. De son engagement dans la lutte démocratique à ses responsabilités internationales, son parcours aura été placé sous le signe de l’éducation, de la culture, de la démocratie et de l’unité africaine.
Né en 1946, Adama Samassékou appartient à une génération d’intellectuels maliens profondément engagés dans les transformations politiques et sociales de leur pays. Formé à l’Université d’État Lomonossov de Moscou, où il obtient un diplôme en philologie et linguistique, il poursuit ensuite ses études en France, notamment à la Sorbonne et à l’Université Paris-Dauphine. Cette solide formation universitaire va contribuer à forger le profil d''un homme à la croisée de la politique, des sciences humaines et de la culture.
De l''engagement clandestin à la démocratie
Avant de connaître les responsabilités gouvernementales, Adama Samassékou s''engage dans la contestation du régime de Moussa Traoré. Dans les années 1970, alors que le Mali est dirigé par un régime militaire, il fait partie des intellectuels arrêtés pour leur engagement politique.
Cette expérience marque durablement son parcours. Lorsque le Mali s''engage sur la voie de la démocratie au début des années 1990, Samassékou devient naturellement l''un des acteurs de cette nouvelle page de l''histoire politique nationale.
Il participe à la structuration de l''Alliance pour la démocratie au Mali – Parti africain pour la solidarité et la justice (ADEMA-PASJ) et devient notamment président fondateur de la section ADEMA-France.
L''arrivée au pouvoir d''Alpha Oumar Konaré ouvre alors une nouvelle étape de sa carrière.
Sept années au ministère de l''Éducation
En 1993, Adama Samassékou entre au gouvernement et prend les commandes du ministère de l''Éducation. Il conservera ce portefeuille pendant près de sept ans, jusqu''en 2000.
Son profil de linguiste et de spécialiste des sciences humaines donne une orientation particulière à son action. L''éducation, la formation, l''alphabétisation et la valorisation des langues nationales occupent une place importante dans sa vision du développement du Mali.
De 1997 à 2000, il devient également porte-parole du gouvernement. Il s''impose alors comme l''une des principales voix publiques du pouvoir d''Alpha Oumar Konaré, défendant et expliquant les politiques gouvernementales dans une période où le Mali cherche à consolider son expérience démocratique.
Des responsabilités nationales à une ambition africaine
Après son départ du gouvernement, Samassékou ne quitte pas pour autant la vie publique. Son engagement se déplace progressivement vers un domaine qui deviendra l''un des grands combats de son existence : la défense et la promotion des langues africaines.
Il joue un rôle déterminant dans la création de l''Académie africaine des langues (ACALAN). Son ambition est claire : faire des langues africaines des instruments de transmission du savoir, de développement, d''éducation et d''intégration du continent.
Pour lui, la question linguistique n''est pas simplement culturelle. Elle touche directement à l''identité et à la souveraineté des peuples africains.
Cette conviction l''amène à défendre une Afrique capable de moderniser ses sociétés sans renoncer à ses langues, à ses cultures et à ses systèmes de pensée.
Une voix malienne dans les grandes instances internationales
La dimension internationale de son parcours va progressivement prendre de l''ampleur.
En 2002, Adama Samassékou est élu président du Comité préparatoire de la phase de Genève du Sommet mondial sur la société de l''information (SMSI). Il se retrouve ainsi au cœur des discussions internationales consacrées à l''avenir du numérique et de la société de l''information.
Cette responsabilité illustre l''étendue de son parcours : le ministre malien de l''Éducation devient désormais l''une des personnalités africaines intervenant dans les grands débats mondiaux sur les technologies, la connaissance et la diversité culturelle.
Il défend notamment la nécessité de préserver la diversité linguistique dans l''espace numérique, estimant que l''Afrique ne devait pas entrer dans la société de l''information en abandonnant ses propres langues.
Un intellectuel panafricain
Au fil des années, Adama Samassékou s''impose comme une figure du panafricanisme intellectuel.
Ses responsabilités au sein de l''Académie africaine des langues, de réseaux consacrés à la diversité linguistique, de la Francophonie et d''organisations internationales de sciences humaines témoignent d''une conception du panafricanisme qui dépasse les seuls enjeux politiques.
Son combat porte également sur la transmission des savoirs, la reconnaissance des cultures africaines et la capacité des sociétés du continent à produire leur propre pensée.
Chez lui, la langue devient ainsi un instrument de souveraineté.
Un retour au cœur des préoccupations nationales
Même après ses grandes responsabilités internationales, Samassékou reste attentif à la situation de son pays.
Il continue d''intervenir dans la vie publique malienne et demeure une figure historique de l''ADEMA. Il exerce également des responsabilités de conseil auprès des autorités maliennes.
En 2024, alors que le Mali traverse une période particulièrement délicate de son histoire politique, son expérience est de nouveau sollicitée.
Il est désigné porte-parole du Comité de pilotage du Dialogue inter-Maliens pour la paix et la réconciliation nationale.
Ce choix apparaît comme le prolongement naturel d''une vie consacrée au dialogue, à l''éducation et à la construction d''une société malienne plus consciente de son histoire et de ses identités.
Mais il n''aura pas le temps d''achever cette dernière mission.
La disparition d''une figure de la vie publique malienne
Adama Samassékou s''éteint à Bamako le 23 février 2024, à l''âge de 77 ans.
Sa disparition suscite de nombreux hommages au Mali et au sein des milieux intellectuels africains et internationaux.
Il laisse derrière lui l''image d''un homme dont le parcours aura traversé plusieurs époques du Mali contemporain : la période de la dictature militaire, la lutte démocratique, les années de construction institutionnelle, l''ouverture internationale et les nouvelles interrogations autour de l''avenir du pays.
Un héritage qui dépasse la politique
Adama Samassékou aura été ministre, porte-parole du gouvernement, militant politique, linguiste, diplomate intellectuel et défenseur des langues africaines.
Mais réduire son parcours à ses fonctions officielles serait probablement passer à côté de l''essentiel.
Son véritable héritage réside dans une conviction qui aura accompagné toute son existence : l''Afrique peut se moderniser sans renoncer à ce qui constitue son identité.
L''éducation, la culture, les langues, la démocratie et la transmission du savoir auront ainsi constitué les différents visages d''un même combat.
Au Mali, son nom demeure aujourd''hui associé à cette vision. Et le fait qu''une salle du Palais des Pionniers porte son nom constitue, à sa manière, un rappel de cette philosophie : former la jeunesse, transmettre le savoir et préparer les générations futures à prendre leur place dans la construction du pays.
Adama Samassékou n''aura donc pas seulement servi l''État malien. Il aura consacré une grande partie de sa vie à une ambition plus vaste : contribuer à faire de la connaissance, de la culture et de l''identité africaine des instruments de développement et de souveraineté.
— Fin du document —';

UPDATE personnalites SET parcours = @bio WHERE CONCAT_WS(' ', prenom, nom) LIKE '%Adama%' AND CONCAT_WS(' ', prenom, nom) LIKE '%Samassekou%';

INSERT INTO personnalites (nom, prenom, titre, photo, parcours, espace_id, ordre)
SELECT 'Samassékou', 'Adama', NULL, NULL, @bio,
       (SELECT id FROM espaces WHERE nom LIKE '%Adama%Samassekou%' ORDER BY id LIMIT 1),
       (SELECT COALESCE(MAX(p.ordre), -1) + 1 FROM personnalites p)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM personnalites WHERE CONCAT_WS(' ', prenom, nom) LIKE '%Adama%' AND CONCAT_WS(' ', prenom, nom) LIKE '%Samassekou%');

COMMIT;
