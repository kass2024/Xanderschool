<?php
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
		<a class="gx-brand" href="#home-section" aria-label="Xander Tech">
			<img class="gx-logo" src="<?= base_url('assets/images/xander-tech-wordmark.jpg'); ?>" alt="Xander Tech LLC">
		</a>
		<nav class="gx-links" id="gxLinks" aria-label="Primary">
			<a href="#home-section">Overview</a>
			<a class="gx-on" href="#packages">Packages</a>
			<a href="#modules">Modules</a>
			<a href="<?= base_url('student-marks'); ?>"><?= lang('app.studentMarks'); ?></a>
			<a href="<?= base_url('application'); ?>"><?= lang('app.onlineRegistration'); ?></a>
			<span class="gx-lang">
				<a href="javascript:void(0)" class="lang_switcher" data-target="en"><img src="<?= base_url('assets/images/en-flag.png'); ?>" alt=""> EN</a>
				<span>|</span>
				<a href="javascript:void(0)" class="lang_switcher" data-target="fr"><img src="<?= base_url('assets/images/fr-flag.png'); ?>" alt=""> FR</a>
			</span>
			<a class="gx-quote" href="#contact-section">Get a quote</a>
			<a class="gx-login" href="<?= base_url('login'); ?>"><?= lang('app.login'); ?></a>
		</nav>
		<button class="gx-hamb" type="button" aria-label="Open menu" aria-expanded="false" id="gxHamb">☰</button>
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
		<h2>Choose a plan that grows with your school</h2>
		<p class="gx-copy">A refined pricing experience with the menu always in view, and a clear list of what each package includes.</p>
		<div class="gx-plan-banner">
			<div>
				<h3>One platform. Every school operation.</h3>
				<p>Student records / Academics / Finances / Identification / Operations</p>
			</div>
			<a class="gx-choose" href="#packages" style="background:linear-gradient(180deg,#E8C56A,#C9962A);border-radius:999px;padding:14px 22px;font-weight:800;color:#1C2128;white-space:nowrap">Explore packages</a>
		</div>
		<div class="gx-pkgs" id="gxPkgs">
			<article class="gx-pkg" data-plan="trial">
				<div class="gx-tag">FREE TRIAL</div>
				<h3>Trial</h3>
				<p class="desc">Explore before committing.</p>
				<div class="gx-price">Free<small>Proposed: 30 days</small></div>
				<ul>
					<li><span class="plus">+</span> 5 demo modules</li>
					<li><span class="plus">+</span> Up to 50 students</li>
					<li><span class="plus">+</span> Upgrade anytime</li>
				</ul>
				<button class="gx-choose" type="button" data-plan="trial">Choose package</button>
			</article>
			<article class="gx-pkg" data-plan="essential">
				<div class="gx-tag">PACKAGE A</div>
				<h3>Essential</h3>
				<p class="desc">Core school administration.</p>
				<div class="gx-price">$22.87<small>per student / year</small></div>
				<ul>
					<li><span class="plus">+</span> 10 core modules</li>
					<li><span class="plus">+</span> Student and staff records</li>
					<li><span class="plus">+</span> Attendance and reports</li>
				</ul>
				<button class="gx-choose" type="button" data-plan="essential">Choose package</button>
			</article>
			<article class="gx-pkg featured" data-plan="professional">
				<div class="gx-tag">PACKAGE B</div>
				<h3>Professional</h3>
				<p class="desc">Connected school operations.</p>
				<div class="gx-price">$37.98<small>per student / year</small></div>
				<ul>
					<li><span class="plus">+</span> 19 modules</li>
					<li><span class="plus">+</span> Fees and finances</li>
					<li><span class="plus">+</span> Pocket money and transport</li>
				</ul>
				<button class="gx-choose" type="button" data-plan="professional">Selected plan</button>
			</article>
			<article class="gx-pkg" data-plan="complete">
				<div class="gx-tag">PACKAGE C</div>
				<h3>Complete</h3>
				<p class="desc">Advanced school capabilities.</p>
				<div class="gx-price">$51.78<small>per student / year</small></div>
				<ul>
					<li><span class="plus">+</span> 23 assigned modules</li>
					<li><span class="plus">+</span> Facial recognition</li>
					<li><span class="plus">+</span> Custom workflows</li>
				</ul>
				<button class="gx-choose" type="button" data-plan="complete">Choose package</button>
			</article>
		</div>
		<div class="gx-picked" id="gxPicked">
			<div class="gx-picked-mods">
				<div class="gx-tag" id="gxPickedTag">PACKAGE B</div>
				<h3 id="gxPickedTitle">Professional</h3>
				<p class="gx-picked-count" id="gxPickedCount"></p>
				<ul id="gxPickedList"></ul>
			</div>
			<form class="gx-pay" id="gxPay" method="post" action="#">
				<h3>Pay for this package</h3>
				<p class="gx-pay-lead">Same payment methods as Xander Learning Hub: Mobile Money on your phone, or a card through Stripe.</p>
				<div class="gx-methods" id="gxMethods">
					<button type="button" class="on" data-method="momo">Mobile Money</button>
					<button type="button" data-method="stripe">Card · Stripe</button>
				</div>
				<p class="gx-momo-funds" id="gxMomoFunds">Funds are received on MoMo: 0781 976 400</p>
				<label class="gx-field" id="gxStudentsWrap">Number of students
					<input type="number" min="1" max="20000" value="100" id="gxStudents" inputmode="numeric">
				</label>
				<p class="gx-total" id="gxTotal"></p>
				<label class="gx-field">Your name
					<input id="gxPayName" autocomplete="name" placeholder="School contact name">
				</label>
				<label class="gx-field">Email for PDF receipt
					<input type="email" id="gxPayEmail" required placeholder="you@school.com" autocomplete="email">
				</label>
				<label class="gx-field" id="gxPhoneWrap">Mobile Money number
					<input id="gxPayPhone" inputmode="tel" placeholder="0788 XXX XXX" autocomplete="tel">
				</label>
				<button class="gx-pay-btn" type="submit" id="gxPayBtn">Pay with Mobile Money</button>
				<ol class="gx-how" id="gxHow">
					<li>Choose a package and the number of students.</li>
					<li>Enter the email that should receive the PDF receipt.</li>
					<li id="gxHowPay">Approve Mobile Money to 0781 976 400, the same receiver as Xander Learning Hub.</li>
				</ol>
				<p class="gx-pay-status" id="gxPayStatus" hidden></p>
			</form>
		</div>
		<h3 id="modules" style="margin-top:48px">Compare actual school modules</h3>
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
	var plans = {
		trial: { tag: 'FREE TRIAL', name: 'Trial', price: 0, unit: 'Proposed: 30 days', col: 0 },
		essential: { tag: 'PACKAGE A', name: 'Essential', price: 22.87, unit: 'per student / year', col: 1 },
		professional: { tag: 'PACKAGE B', name: 'Professional', price: 37.98, unit: 'per student / year', col: 2 },
		complete: { tag: 'PACKAGE C', name: 'Complete', price: 51.78, unit: 'per student / year', col: 3 }
	};
	var moduleRows = <?= json_encode($modules, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
	var currentPlan = 'professional';
	var payMethod = 'momo';
	var picked = document.getElementById('gxPicked');
	var studentsInput = document.getElementById('gxStudents');
	var momoFunds = document.getElementById('gxMomoFunds');
	var phoneWrap = document.getElementById('gxPhoneWrap');
	var studentsWrap = document.getElementById('gxStudentsWrap');
	var methods = document.getElementById('gxMethods');
	var payBtn = document.getElementById('gxPayBtn');
	var howPay = document.getElementById('gxHowPay');
	var totalEl = document.getElementById('gxTotal');
	var statusEl = document.getElementById('gxPayStatus');

	function money(amount) {
		return '$' + amount.toFixed(2);
	}

	function selectPlan(id, scroll) {
		if (!plans[id]) return;
		currentPlan = id;
		document.querySelectorAll('.gx-pkg').forEach(function (card) {
			var on = card.getAttribute('data-plan') === id;
			card.classList.toggle('featured', on);
			var btn = card.querySelector('.gx-choose');
			if (btn) btn.textContent = on ? 'Selected plan' : 'Choose package';
		});
		var plan = plans[id];
		document.getElementById('gxPickedTag').textContent = plan.tag;
		document.getElementById('gxPickedTitle').textContent = plan.name + ' modules';
		var included = moduleRows.filter(function (row) { return row[plan.col + 1]; });
		document.getElementById('gxPickedCount').textContent = included.length + (included.length === 1 ? ' module included' : ' modules included');
		var list = document.getElementById('gxPickedList');
		list.textContent = '';
		included.forEach(function (row) {
			var item = document.createElement('li');
			item.textContent = row[0];
			list.appendChild(item);
		});
		document.querySelectorAll('.gx-table tr').forEach(function (tr) {
			Array.prototype.forEach.call(tr.children, function (cell, index) {
				cell.classList.toggle('gx-col-on', index === plan.col + 1);
			});
		});
		var trial = plan.price === 0;
		if (studentsWrap) studentsWrap.hidden = trial;
		if (methods) methods.hidden = trial;
		renderPay();
		if (statusEl) {
			statusEl.hidden = true;
			statusEl.textContent = '';
		}
		if (scroll && picked) picked.scrollIntoView({ behavior: 'smooth', block: 'start' });
	}

	function renderPay() {
		var plan = plans[currentPlan];
		var students = Math.max(1, parseInt(studentsInput && studentsInput.value, 10) || 1);
		var trial = plan.price === 0;
		var momo = payMethod === 'momo';
		if (momoFunds) momoFunds.hidden = trial || !momo;
		if (phoneWrap) phoneWrap.hidden = trial || !momo;
		if (totalEl) {
			totalEl.textContent = trial
				? 'Free · up to 50 students'
				: money(plan.price * students) + ' · ' + students + ' students ' + plan.unit;
		}
		if (payBtn) payBtn.textContent = trial ? 'Request free trial' : (momo ? 'Pay with Mobile Money' : 'Pay safely with Stripe');
		if (howPay) {
			howPay.textContent = trial
				? 'Tell us the school name and we will confirm the 30-day trial.'
				: (momo
					? 'Pay Mobile Money to 0781 976 400, the same receiver as Xander Learning Hub, then approve it on your phone.'
					: 'Card payments use Stripe, the same secure checkout as Xander Learning Hub. We email the payment link to you.');
		}
		document.querySelectorAll('#gxMethods button').forEach(function (button) {
			button.classList.toggle('on', button.getAttribute('data-method') === payMethod);
		});
	}

	document.querySelectorAll('.gx-pkg').forEach(function (card) {
		card.addEventListener('click', function () {
			selectPlan(card.getAttribute('data-plan'), true);
		});
	});
	if (studentsInput) studentsInput.addEventListener('input', renderPay);
	if (methods) {
		methods.addEventListener('click', function (event) {
			var button = event.target.closest('button');
			if (!button) return;
			payMethod = button.getAttribute('data-method') || 'momo';
			renderPay();
		});
	}
	var payForm = document.getElementById('gxPay');
	if (payForm) {
		payForm.addEventListener('submit', function (event) {
			event.preventDefault();
			event.stopPropagation();
			var plan = plans[currentPlan];
			var students = Math.max(1, parseInt(studentsInput && studentsInput.value, 10) || 1);
			var name = (document.getElementById('gxPayName').value || '').trim();
			var email = (document.getElementById('gxPayEmail').value || '').trim();
			var phone = (document.getElementById('gxPayPhone').value || '').trim();
			var trial = plan.price === 0;
			var momo = payMethod === 'momo';
			if (!trial && momo && phone.replace(/\D/g, '').length < 9) {
				if (statusEl) {
					statusEl.hidden = false;
					statusEl.textContent = 'Enter the Mobile Money number that should approve this payment.';
				}
				document.getElementById('gxPayPhone').focus();
				return;
			}
			var amount = trial ? 'Free' : money(plan.price * students);
			var method = trial ? 'Free trial' : (momo ? 'Mobile Money' : 'Stripe card');
			var lines = [
				'Package: ' + plan.name,
				'Students: ' + (trial ? 'up to 50' : String(students)),
				'Amount: ' + amount,
				'Method: ' + method,
				'Name: ' + name,
				'Receipt email: ' + email
			];
			if (!trial && momo) {
				lines.push('Mobile Money number: ' + phone);
				lines.push('Pay to MoMo: 0781 976 400');
			}
			var subject = (trial ? 'SmartSMS trial' : 'SmartSMS payment') + ' — ' + plan.name;
			window.location.href = 'mailto:admissions@xanderglobalscholars.com?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(lines.join('\n'));
			if (statusEl) {
				statusEl.hidden = false;
				statusEl.textContent = trial
					? 'Trial request opened for ' + email + '. We will confirm the 30-day Trial and its modules.'
					: (momo
						? 'Pay ' + amount + ' with Mobile Money to 0781 976 400 from ' + phone + '. Your receipt request for ' + email + ' is ready to send.'
						: 'Stripe checkout for ' + amount + ' will be emailed to ' + email + ', the same card method as Xander Learning Hub.');
			}
		});
	}
	selectPlan('professional', false);

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
