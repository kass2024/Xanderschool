<?php $goldHome = empty($type) && empty($page); ?>
<!doctype html>
<html lang="<?= esc($_COOKIE['lang'] ?? 'en'); ?>">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
	<title>SmartSMS — XanderTech Smart School Management System</title>
	<meta name="description" content="XanderTech SmartSMS — manage students, attendance, exams, fees, and parent communication in one smart school platform.">
	<link rel="icon" href="<?= base_url('assets/images/xander-x-3d.png'); ?>">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<?php if ($goldHome): ?>
		<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&family=Manrope:wght@500;700;800&display=swap" rel="stylesheet">
		<link rel="stylesheet" href="<?= base_url('assets/css/smartsms-gold.css'); ?>?v=6">
	<?php else: ?>
		<link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,550;9..144,650&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
		<noscript><link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,550;9..144,650&family=Outfit:wght@400;500;600;700&display=swap" rel="stylesheet"></noscript>
		<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
		<link rel="stylesheet" href="<?= base_url('assets/css/smartsms-landing.css'); ?>">
	<?php endif; ?>
	<?php if (!empty($type)): ?>
		<link rel="stylesheet" href="<?= base_url('assets/landing/css/bootstrap.min.css'); ?>">
		<link rel="stylesheet" href="<?= base_url('assets/landing/css/style.css'); ?>">
	<?php endif; ?>
</head>
<body class="<?= $goldHome ? 'gold-home' : 'ss-landing'; ?><?= empty($type) ? '' : ' ss-has-type'; ?>">

<?php if (empty($type) && !$goldHome): ?>
<header class="ss-nav" id="ssNav" role="banner">
	<div class="ss-nav__inner">
		<a class="ss-brand" href="<?= base_url('/'); ?>" aria-label="SmartSMS home">
			<img src="<?= base_url('assets/images/xander-wordmark-3d.png'); ?>" alt="XanderTech SmartSMS" style="background:#000;border-radius:8px;padding:4px;">
			<span class="ss-brand__text">
				<strong>SmartSMS</strong>
				<span>XanderTech</span>
			</span>
		</a>

		<nav class="ss-nav__desktop" aria-label="Primary">
			<ul class="ss-nav__links" id="desktopMenu">
				<li><a href="<?= base_url('/'); ?>#home-section"><?= lang('app.home'); ?></a></li>
				<li><a href="<?= base_url('student-marks'); ?>" class="<?= (isset($page) && strtolower((string)$page) === 'marks') ? 'is-active' : ''; ?>"><?= lang('app.studentMarks'); ?></a></li>
				<li><a href="<?= base_url('application'); ?>" class="<?= (isset($page) && strtolower((string)$page) === 'application') ? 'is-active' : ''; ?>"><?= lang('app.onlineRegistration'); ?></a></li>
				<li><a href="<?= base_url('/'); ?>#about-section"><?= lang('app.about'); ?></a></li>
				<li><a href="<?= base_url('/'); ?>#services-section"><?= lang('app.features'); ?></a></li>
				<li><a href="<?= base_url('/'); ?>#contact-section"><?= lang('app.contact'); ?></a></li>
			</ul>
		</nav>

		<div class="ss-nav__actions">
			<div class="ss-lang">
				<a href="javascript:void(0)" class="lang_switcher" data-target="en"><img src="<?= base_url('assets/images/en-flag.png'); ?>" alt=""> En</a>
				<a href="javascript:void(0)" class="lang_switcher" data-target="fr"><img src="<?= base_url('assets/images/fr-flag.png'); ?>" alt=""> Fr</a>
			</div>
			<a class="ss-btn ss-btn--ghost" href="<?= base_url('login'); ?>"><?= lang('app.login'); ?></a>
			<button class="ss-burger" id="ssBurger" type="button" aria-label="Open menu" aria-controls="ssDrawer" aria-expanded="false">
				<span></span><span></span><span></span>
			</button>
		</div>
	</div>
</header>

<div class="ss-drawer" id="ssDrawer" aria-hidden="true">
	<div class="ss-drawer__scrim" id="ssScrim"></div>
	<div class="ss-drawer__panel" role="dialog" aria-label="Mobile menu">
		<button class="ss-drawer__close" id="ssClose" type="button" aria-label="Close menu">&times;</button>
		<ul class="ss-drawer__links" id="ssDrawerLinks"></ul>
		<div class="ss-lang">
			<a href="javascript:void(0)" class="lang_switcher" data-target="en"><img src="<?= base_url('assets/images/en-flag.png'); ?>" alt=""> English</a>
			<a href="javascript:void(0)" class="lang_switcher" data-target="fr"><img src="<?= base_url('assets/images/fr-flag.png'); ?>" alt=""> Français</a>
		</div>
		<a class="ss-btn ss-btn--primary" href="<?= base_url('login'); ?>"><?= lang('app.login'); ?></a>
	</div>
</div>
<?php endif; ?>

<?= $content; ?>

<?php if (empty($type) && !$goldHome): ?>
<footer class="ss-footer">
	<div class="ss-wrap ss-footer__grid">
		<div>
			<h4>XanderTech SmartSMS</h4>
			<p>Smart School Management System for attendance, exams, fees, and parent communication — flexible for every school.</p>
		</div>
		<div>
			<h4><?= lang('app.links'); ?></h4>
			<ul>
				<li><a href="<?= base_url('/'); ?>#about-section"><?= lang('app.aboutUs'); ?></a></li>
				<li><a href="<?= base_url('/'); ?>#services-section"><?= lang('app.features'); ?></a></li>
				<li><a href="<?= base_url('application'); ?>" class="<?= (isset($page) && strtolower((string)$page) === 'application') ? 'is-active' : ''; ?>"><?= lang('app.onlineRegistration'); ?></a></li>
				<li><a href="<?= base_url('login'); ?>"><?= lang('app.login'); ?></a></li>
			</ul>
		</div>
		<div>
			<h4><?= lang('app.contactUs'); ?></h4>
			<ul>
				<li>XanderTech LLC</li>
				<li><a href="tel:+250783609519">+250 783 609 519</a></li>
				<li><a href="mailto:admissions@xanderglobalscholars.com">admissions@xanderglobalscholars.com</a></li>
			</ul>
		</div>
	</div>
	<div class="ss-wrap ss-footer__copy">
		&copy; <span id="ssYear"></span> XanderTech · SmartSMS. All rights reserved.
	</div>
</footer>

<script src="<?= base_url('assets/landing/js/jquery-3.3.1.min.js'); ?>"></script>
<script>
(function () {
	var nav = document.getElementById('ssNav');
	var body = document.body;
	var burger = document.getElementById('ssBurger');
	var drawer = document.getElementById('ssDrawer');
	var closeBtn = document.getElementById('ssClose');
	var scrim = document.getElementById('ssScrim');
	var drawerLinks = document.getElementById('ssDrawerLinks');
	var year = document.getElementById('ssYear');
	if (year) year.textContent = new Date().getFullYear();

	// Keep header solid/fixed for readability while scrolling
	if (nav) nav.classList.add('is-solid');
	window.addEventListener('scroll', function () {
		if (!nav) return;
		nav.classList.add('is-solid');
	}, { passive: true });

	function buildDrawer() {
		if (!drawerLinks) return;
		drawerLinks.innerHTML = '';
		document.querySelectorAll('#desktopMenu a').forEach(function (a) {
			var li = document.createElement('li');
			var link = document.createElement('a');
			link.href = a.getAttribute('href') || '#';
			link.textContent = a.textContent;
			li.appendChild(link);
			drawerLinks.appendChild(li);
		});
	}

	function openNav() {
		buildDrawer();
		body.classList.add('nav-open');
		if (burger) burger.setAttribute('aria-expanded', 'true');
		if (drawer) drawer.setAttribute('aria-hidden', 'false');
	}
	function closeNav() {
		body.classList.remove('nav-open');
		if (burger) burger.setAttribute('aria-expanded', 'false');
		if (drawer) drawer.setAttribute('aria-hidden', 'true');
	}

	if (burger) burger.addEventListener('click', function () {
		body.classList.contains('nav-open') ? closeNav() : openNav();
	});
	if (closeBtn) closeBtn.addEventListener('click', closeNav);
	if (scrim) scrim.addEventListener('click', closeNav);

	document.addEventListener('click', function (e) {
		var a = e.target.closest('a');
		if (!a) return;
		var href = a.getAttribute('href') || '';
		var hash = '';
		if (href.charAt(0) === '#' && href.length > 1) {
			hash = href;
		} else {
			try {
				var u = new URL(a.href, window.location.href);
				var herePath = window.location.pathname.replace(/\/+$/, '') || '/';
				var therePath = u.pathname.replace(/\/+$/, '') || '/';
				var onHome = (herePath === '/' || /\/index\.php$/i.test(herePath));
				var toHome = (therePath === '/' || /\/index\.php$/i.test(therePath));
				if (onHome && toHome && u.hash && u.hash.length > 1) {
					hash = u.hash;
				}
			} catch (err) {}
		}
		if (hash) {
			var el = document.querySelector(hash);
			if (!el) return;
			e.preventDefault();
			closeNav();
			var top = el.getBoundingClientRect().top + window.pageYOffset - 76;
			window.scrollTo({ top: top, behavior: 'smooth' });
			if (history && history.replaceState) {
				history.replaceState(null, '', hash);
			}
		} else if (body.classList.contains('nav-open') && a.closest('#ssDrawer')) {
			closeNav();
		}
	});

	// Arrive from another page with /#about-section etc.
	if (window.location.hash && window.location.hash.length > 1) {
		setTimeout(function () {
			var el = document.querySelector(window.location.hash);
			if (!el) return;
			var top = el.getBoundingClientRect().top + window.pageYOffset - 76;
			window.scrollTo({ top: top, behavior: 'smooth' });
		}, 120);
	}

	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape') closeNav();
	});

	// Reveal on scroll
	var nodes = document.querySelectorAll('.ss-reveal');
	if ('IntersectionObserver' in window) {
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					entry.target.classList.add('is-in');
					io.unobserve(entry.target);
				}
			});
		}, { threshold: 0.15 });
		nodes.forEach(function (n) { io.observe(n); });
	} else {
		nodes.forEach(function (n) { n.classList.add('is-in'); });
	}

	// Hero education slideshow
	(function initHeroSlider() {
		var slides = Array.prototype.slice.call(document.querySelectorAll('#ssHeroSlider .ss-hero__slide'));
		var dots = Array.prototype.slice.call(document.querySelectorAll('#ssHeroDots .ss-hero__dot'));
		if (!slides.length) return;
		var idx = slides.findIndex(function (s) { return s.classList.contains('is-active'); });
		if (idx < 0) idx = 0;
		var timer = null;

		function goTo(next) {
			if (!slides.length) return;
			idx = ((next % slides.length) + slides.length) % slides.length;
			slides.forEach(function (s, i) { s.classList.toggle('is-active', i === idx); });
			dots.forEach(function (d, i) {
				var on = i === idx;
				d.classList.toggle('is-active', on);
				d.setAttribute('aria-selected', on ? 'true' : 'false');
			});
		}
		function next() { goTo(idx + 1); }
		function start() {
			stop();
			timer = window.setInterval(next, 5500);
		}
		function stop() {
			if (timer) window.clearInterval(timer);
			timer = null;
		}

		dots.forEach(function (dot) {
			dot.addEventListener('click', function () {
				var n = parseInt(dot.getAttribute('data-index'), 10);
				if (!isNaN(n)) goTo(n);
				start();
			});
		});

		var hero = document.getElementById('home-section');
		if (hero) {
			hero.addEventListener('mouseenter', stop);
			hero.addEventListener('mouseleave', start);
		}
		document.addEventListener('visibilitychange', function () {
			document.hidden ? stop() : start();
		});
		start();
	})();

	// Language
	$(document).on('click', '.lang_switcher', function () {
		var lang = $(this).data('target');
		$.getJSON('<?= base_url('set_lang/'); ?>' + lang, function (json) {
			if (json && json.success) location.reload();
			else alert('Changing language failed');
		});
	});
})();
</script>
<?php endif; ?>
</body>
</html>
