<?php
$logo = base_url('assets/images/xander-tech-gold.jpg');
$modules = [
	['Student records', 1, 1, 1, 1],
	['Classes and courses', 1, 1, 1, 1],
	['Attendance', 1, 1, 1, 1],
	['Staff and HR', 0, 1, 1, 1],
	['Marks and reports', 1, 1, 1, 1],
	['Discipline and permissions', 0, 1, 1, 1],
	['Parent visits', 0, 1, 1, 1],
	['Communication', 0, 1, 1, 1],
	['Card management', 0, 1, 1, 1],
	['School dashboard', 1, 1, 1, 1],
	['School fees', 0, 0, 1, 1],
	['Finances', 0, 0, 1, 1],
	['School assets', 0, 0, 1, 1],
	['Library', 0, 0, 1, 1],
	['Transport', 0, 0, 1, 1],
	['Pocket money', 0, 0, 1, 1],
	['Teaching plans', 0, 0, 1, 1],
	['Timetables', 0, 0, 1, 1],
	['Advanced reports', 0, 0, 1, 1],
	['Facial recognition integration', 0, 0, 0, 1],
	['Advanced digital identification', 0, 0, 0, 1],
	['Customized workflows', 0, 0, 0, 1],
	['Full customization', 0, 0, 0, 1],
];
$mark = static function (int $on): string {
	return $on ? '<span class="yes">✓</span>' : '—';
};
?>
<header class="gx-head">
	<div class="gx-wrap gx-bar">
		<a class="gx-logo" href="#home-section" aria-label="Xander Tech SmartSMS">
			<img src="<?= esc($logo); ?>" alt="Xander Tech LLC">
		</a>
		<nav class="gx-links" id="gxLinks" aria-label="Primary">
			<a href="#home-section"><?= lang('app.home'); ?></a>
			<a href="<?= base_url('student-marks'); ?>"><?= lang('app.studentMarks'); ?></a>
			<a href="<?= base_url('application'); ?>"><?= lang('app.onlineRegistration'); ?></a>
			<a href="#about-section"><?= lang('app.about'); ?></a>
			<a href="#services-section"><?= lang('app.features'); ?></a>
			<a href="#packages">Packages</a>
			<a href="#contact-section"><?= lang('app.contact'); ?></a>
			<span class="gx-lang">
				<a href="javascript:void(0)" class="lang_switcher" data-target="en"><img src="<?= base_url('assets/images/en-flag.png'); ?>" alt=""> EN</a>
				<span>|</span>
				<a href="javascript:void(0)" class="lang_switcher" data-target="fr"><img src="<?= base_url('assets/images/fr-flag.png'); ?>" alt=""> FR</a>
			</span>
			<a class="gx-login" href="<?= base_url('login'); ?>"><?= lang('app.login'); ?></a>
		</nav>
		<button class="gx-hamb" type="button" aria-label="Toggle navigation" aria-expanded="false" id="gxHamb">☰</button>
	</div>
</header>

<section class="gx-hero" id="home-section">
	<div class="gx-wrap gx-hero-grid">
		<div>
			<p class="gx-eyebrow">Modern school management, simplified</p>
			<h1>One smart platform.<br><span>Every school need.</span></h1>
			<p class="gx-lead">Bring students, staff, academics, attendance, finances and parent communication together in one beautifully simple school management experience.</p>
			<div class="gx-actions">
				<a class="gx-btn gx-btn-primary" href="<?= base_url('application'); ?>">Start Online Registration →</a>
				<a class="gx-btn gx-btn-ghost" href="<?= base_url('login'); ?>">Access School Portal</a>
			</div>
			<p class="gx-checks">✓ Secure records &nbsp; ✓ Smarter reporting &nbsp; ✓ Connected families</p>
		</div>
		<aside class="gx-dash" aria-label="School overview">
			<div class="gx-dash__top"><span>School overview</span><span>Dashboard preview</span></div>
			<div class="gx-stats">
				<div class="gx-stat"><span>Students</span><strong>1,248</strong></div>
				<div class="gx-stat"><span>Attendance</span><strong>96%</strong></div>
				<div class="gx-stat"><span>Classes</span><strong>32</strong></div>
			</div>
			<div class="gx-bars">
				<p>Academic performance overview</p>
				<div class="gx-bar"><i style="width:86%"></i></div>
				<div class="gx-bar"><i style="width:72%"></i></div>
				<div class="gx-bar"><i style="width:64%"></i></div>
			</div>
		</aside>
	</div>
</section>

<section class="gx-section" id="about-section">
	<div class="gx-wrap gx-split">
		<div>
			<div class="gx-kicker">About SmartSMS</div>
			<h2>Built for the way modern schools work.</h2>
			<p class="gx-copy">SmartSMS helps school administrators, teachers and families stay aligned — from student records and class structures to marks, fees and messaging.</p>
			<ul class="gx-list">
				<li>Student and staff records in one secure place</li>
				<li>Attendance, exams and reports without spreadsheets</li>
				<li>Clearer communication with parents and guardians</li>
			</ul>
		</div>
		<div class="gx-panel">
			<strong>Everything connected</strong>
			<p class="gx-copy">Students · Staff · Academics · Families</p>
			<p class="gx-copy">One record for the school day: who is present, how they are progressing, what is owed, and what families need to know.</p>
		</div>
	</div>
</section>

<section class="gx-section gx-soft" id="services-section">
	<div class="gx-wrap">
		<div class="gx-kicker">Core capabilities</div>
		<h2>Everything your school runs on.</h2>
		<p class="gx-copy">Powerful tools, thoughtfully organized so your team can focus on education, not paperwork.</p>
		<div class="gx-grid">
			<article class="gx-card"><div class="gx-symbol">▥</div><h3>Insights &amp; dashboards</h3><p>View enrollment, attendance and school performance at a glance.</p></article>
			<article class="gx-card"><div class="gx-symbol">▤</div><h3>Exams &amp; report cards</h3><p>Manage marks, exams and consistent student progress reports.</p></article>
			<article class="gx-card"><div class="gx-symbol">$</div><h3>Fees &amp; accounting</h3><p>Track school fees, payments and outstanding balances clearly.</p></article>
			<article class="gx-card"><div class="gx-symbol">✉</div><h3>Parent communication</h3><p>Keep families informed through school-approved SMS updates.</p></article>
			<article class="gx-card"><div class="gx-symbol">✓</div><h3>Attendance tracking</h3><p>Organize student and staff attendance, permissions and discipline.</p></article>
			<article class="gx-card"><div class="gx-symbol">▦</div><h3>Student management</h3><p>Manage registrations, profiles, classes and academic records.</p></article>
		</div>
	</div>
</section>

<section class="gx-section">
	<div class="gx-wrap">
		<div class="gx-kicker">How it works</div>
		<h2>Simple to start. Powerful to scale.</h2>
		<p class="gx-copy">A clear workflow for school teams, students and families.</p>
		<div class="gx-steps">
			<article class="gx-step"><b>01 / SETUP</b><h3>Configure your school</h3><p>Set classes, staff roles, academic periods and student records.</p></article>
			<article class="gx-step"><b>02 / OPERATE</b><h3>Manage everyday work</h3><p>Take attendance, enter marks, track fees and coordinate teams.</p></article>
			<article class="gx-step"><b>03 / CONNECT</b><h3>Keep families informed</h3><p>Share results, registration information and approved updates.</p></article>
		</div>
		<div class="gx-band" style="margin-top:28px">
			<div>
				<h2 style="color:#fff;margin:0">Ready to make school management easier?</h2>
				<p>Access your school portal or start online registration today.</p>
			</div>
			<div class="gx-actions">
				<a class="gx-btn gx-btn-primary" href="<?= base_url('login'); ?>">School Login →</a>
				<a class="gx-btn gx-btn-light" href="<?= base_url('student-marks'); ?>">View Student Marks</a>
			</div>
		</div>
	</div>
</section>

<section class="gx-section" id="packages">
	<div class="gx-wrap">
		<div class="gx-kicker">Flexible plans for every school</div>
		<h2>Simple pricing. Powerful possibilities.</h2>
		<p class="gx-copy">From essential student records to complete school operations, find the right SmartSMS plan for your school.</p>
		<div class="gx-pkgs">
			<article class="gx-pkg">
				<div class="gx-tag">FREE TRIAL</div>
				<h3>Trial</h3>
				<p class="desc">Proposed 30-day evaluation</p>
				<div class="gx-price">Free<small>Proposed trial terms</small></div>
				<ul>
					<li><span class="yes">✓</span> Up to 50 students (proposed)</li>
					<li><span class="yes">✓</span> Student records and classes</li>
					<li><span class="yes">✓</span> Attendance and marks</li>
					<li><span class="yes">✓</span> School dashboard</li>
				</ul>
				<a class="gx-btn gx-btn-primary" href="#contact-section">Request trial →</a>
			</article>
			<article class="gx-pkg">
				<div class="gx-tag">PACKAGE A</div>
				<h3>Essential</h3>
				<p class="desc">Core school management</p>
				<div class="gx-price">$22.87<small>per student / year · USD</small></div>
				<ul>
					<li><span class="yes">✓</span> 10 core modules</li>
					<li><span class="yes">✓</span> Student and staff attendance</li>
					<li><span class="yes">✓</span> Marks and progress reports</li>
					<li><span class="yes">✓</span> Parent visits and messaging</li>
				</ul>
				<a class="gx-btn gx-btn-primary" href="#contact-section">Request quotation →</a>
			</article>
			<article class="gx-pkg featured">
				<div class="gx-tag">PACKAGE B</div>
				<h3>Professional</h3>
				<p class="desc">Manage daily school operations</p>
				<div class="gx-price">$37.98<small>per student / year · USD</small></div>
				<ul>
					<li><span class="yes">✓</span> All 10 Essential modules</li>
					<li><span class="yes">✓</span> School fees and finances</li>
					<li><span class="yes">✓</span> Pocket money, library, transport</li>
					<li><span class="yes">✓</span> Teaching plans and advanced reports</li>
				</ul>
				<a class="gx-btn gx-btn-primary" href="#contact-section">Request quotation →</a>
			</article>
			<article class="gx-pkg">
				<div class="gx-tag">PACKAGE C</div>
				<h3>Complete</h3>
				<p class="desc">Advanced school solutions</p>
				<div class="gx-price">$51.78<small>per student / year · USD</small></div>
				<ul>
					<li><span class="yes">✓</span> All 19 Professional modules</li>
					<li><span class="yes">✓</span> Facial recognition integration</li>
					<li><span class="yes">✓</span> Advanced digital identification</li>
					<li><span class="yes">✓</span> Customized workflows and full customization</li>
				</ul>
				<a class="gx-btn gx-btn-primary" href="#contact-section">Request quotation →</a>
			</article>
		</div>
		<h3 style="margin-top:42px">Compare all 23 package modules</h3>
		<div class="gx-table-wrap">
			<table class="gx-table">
				<thead>
					<tr>
						<th>Module</th>
						<th>Trial*</th>
						<th>Essential</th>
						<th>Professional</th>
						<th>Complete</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($modules as $row): ?>
						<tr>
							<td><?= esc($row[0]); ?></td>
							<td><?= $mark($row[1]); ?></td>
							<td><?= $mark($row[2]); ?></td>
							<td><?= $mark($row[3]); ?></td>
							<td><?= $mark($row[4]); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<p class="gx-note">*Free Trial is a proposed offering; its duration, limits and access require approval. Official paid rates are per student per year. Setup, devices, cards and separately quoted services may add costs. Installation, training and technical support are included with paid packages.</p>
	</div>
</section>

<section class="gx-section gx-soft" id="contact-section">
	<div class="gx-wrap gx-contact">
		<div>
			<div class="gx-kicker">Let's talk</div>
			<h2>Let's build a smarter school together.</h2>
			<p class="gx-copy">Have a question about SmartSMS? Contact the XanderTech team.</p>
			<div class="gx-meta">
				<div>📍 Kigali, Rwanda</div>
				<div>☎ <a href="tel:+250783609519">+250 783 609 519</a></div>
				<div>✉ <a href="mailto:admissions@xanderglobalscholars.com">admissions@xanderglobalscholars.com</a></div>
			</div>
		</div>
		<form class="gx-form" id="gxContact" method="post" action="#">
			<input name="fname" aria-label="First name" placeholder="First name" required autocomplete="given-name">
			<input name="lname" aria-label="Last name" placeholder="Last name" required autocomplete="family-name">
			<input class="wide" type="email" name="email" aria-label="Email" placeholder="Email address" required autocomplete="email">
			<input class="wide" name="subject" aria-label="Subject" placeholder="Subject" required>
			<textarea name="message" aria-label="Message" placeholder="How can we help?" rows="5" required></textarea>
			<button class="gx-btn gx-btn-primary" type="submit">Send Message →</button>
		</form>
	</div>
</section>

<footer class="gx-foot">
	<div class="gx-wrap">© <?= date('Y'); ?> <strong>XanderTech · SmartSMS</strong>. All rights reserved. School management, made simple.</div>
</footer>
<script>
(function () {
	var hamb = document.getElementById('gxHamb');
	var links = document.getElementById('gxLinks');
	if (hamb && links) {
		hamb.addEventListener('click', function () {
			var open = links.classList.toggle('open');
			hamb.setAttribute('aria-expanded', open ? 'true' : 'false');
		});
		links.addEventListener('click', function (event) {
			if (event.target.closest('a')) links.classList.remove('open');
		});
	}
	var form = document.getElementById('gxContact');
	if (form) {
		form.addEventListener('submit', function (event) {
			event.preventDefault();
			var data = new FormData(form);
			var subject = (data.get('subject') || 'SmartSMS enquiry').toString();
			var body = [
				'Name: ' + (data.get('fname') || '') + ' ' + (data.get('lname') || ''),
				'Email: ' + (data.get('email') || ''),
				'',
				(data.get('message') || '').toString()
			].join('\n');
			window.location.href = 'mailto:admissions@xanderglobalscholars.com?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
		});
	}
	document.querySelectorAll('.lang_switcher').forEach(function (link) {
		link.addEventListener('click', function () {
			var lang = link.getAttribute('data-target');
			fetch('<?= base_url('set_lang/'); ?>' + encodeURIComponent(lang), { headers: { 'Accept': 'application/json' } })
				.then(function (response) { return response.json(); })
				.then(function (json) {
					if (json && json.success) location.reload();
					else alert('Changing language failed');
				})
				.catch(function () { alert('Changing language failed'); });
		});
	});
})();
</script>
