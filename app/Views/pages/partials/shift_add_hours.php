<style>
	.shift-add-hours{display:flex;flex-wrap:wrap;align-items:flex-end;gap:10px;margin-top:8px;}
	.shift-add-hours .shift-day{flex:0 0 140px;}
	.shift-clock{flex:1 1 220px;min-width:210px;}
	.shift-clock-label{display:block;font-size:11px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:#64748b;margin-bottom:4px;}
	.shift-clock-face{display:flex;align-items:center;gap:4px;background:#fff;border:1px solid #d6dee8;border-radius:10px;padding:4px 6px;box-shadow:0 1px 2px rgba(15,23,42,.04);}
	.shift-clock-face select{border:0;background:transparent;font-weight:650;font-size:15px;color:#0f172a;height:34px;padding:0 2px;outline:none;cursor:pointer;}
	.shift-clock-h{width:52px;}
	.shift-clock-m{width:68px;}
	.shift-clock-sep{font-weight:700;color:#94a3b8;}
	.shift-clock-mer{display:flex;background:#f1f5f9;border-radius:8px;padding:2px;margin-left:4px;}
	.shift-clock-mer button{border:0;background:transparent;border-radius:6px;padding:6px 8px;font-size:12px;font-weight:700;color:#64748b;line-height:1;}
	.shift-clock-mer button.is-on{background:#0f766e;color:#fff;}
	.shift-clock-quick{display:flex;gap:4px;margin-top:6px;}
	.shift-clock-quick button{border:1px solid #e2e8f0;background:#fff;border-radius:999px;padding:2px 8px;font-size:11px;font-weight:700;color:#475569;line-height:1.4;}
	.shift-clock-quick button.is-on{background:#ccfbf1;border-color:#0f766e;color:#0f766e;}
	.shift-add-btn{flex:0 0 auto;height:42px;border-radius:10px;padding:0 16px;align-self:flex-start;margin-top:18px;}
	.shift-time-hint{flex:1 0 100%;font-size:12px;color:#64748b;margin:0;}
	.shift-time-hint strong{color:#0f766e;font-weight:700;}
</style>
<div class="add-hours shift-add-hours">
	<div class="shift-day">
		<label class="shift-clock-label"><?= lang("app.days"); ?></label>
		<select class="weekday form-control">
			<option value="0"><?= lang("app.monday"); ?></option>
			<option value="1"><?= lang("app.tuesday"); ?></option>
			<option value="2"><?= lang("app.wednesday"); ?></option>
			<option value="3"><?= lang("app.thursday"); ?></option>
			<option value="4"><?= lang("app.friday"); ?></option>
			<option value="5"><?= lang("app.saturday"); ?></option>
			<option value="6"><?= lang("app.sunday"); ?></option>
		</select>
	</div>
	<?= view('pages/partials/shift_clock', ['role' => 'start', 'decimal' => '9.0']) ?>
	<?= view('pages/partials/shift_clock', ['role' => 'end', 'decimal' => '17.0']) ?>
	<button type="button" class="btn btn-gradient-primary addhours shift-add-btn">
		<span><?= lang("app.addDay"); ?></span>
	</button>
	<p class="shift-time-hint">Pick any minute. For 6:45, choose hour <strong>6</strong>, minute <strong>:45</strong>, then AM or PM.</p>
</div>
<script>
	window.shiftClock = window.shiftClock || {
		toDecimal: function (h12, minute, mer) {
			var h = parseInt(h12, 10) % 12;
			if (String(mer).toLowerCase() === "pm") h += 12;
			var m = parseInt(minute, 10);
			if (isNaN(m)) m = 0;
			m = Math.max(0, Math.min(59, m));
			if (m === 0) return h + ".0";
			if (m === 30) return h + ".5";
			return String(Math.round((h + m / 60) * 10000) / 10000);
		},
		labelFromDecimal: function (raw) {
			var val = parseFloat(raw);
			if (!isFinite(val)) return String(raw);
			var hh = Math.floor(val + 1e-8);
			var mm = Math.round((val - hh) * 60);
			if (mm >= 60) { hh += Math.floor(mm / 60); mm = mm % 60; }
			hh = ((hh % 24) + 24) % 24;
			var mmStr = (mm < 10 ? "0" : "") + mm;
			var s = String(raw).trim();
			if (s === "0" || s === "0.0" || s === "0.00") return "12:00 am (midnight next day)";
			if (Math.abs(val - 12) < 0.001) return "12:00 pm (noon)";
			if (hh === 0) return "12:" + mmStr + " am";
			if (hh === 12) return "12:" + mmStr + " pm";
			if (hh > 12) return (hh - 12) + ":" + mmStr + " pm";
			return hh + ":" + mmStr + " am";
		},
		sync: function ($clock) {
			var mer = $clock.find(".shift-clock-mer button.is-on").data("mer") || "am";
			var dec = this.toDecimal($clock.find(".shift-clock-h").val(), $clock.find(".shift-clock-m").val(), mer);
			$clock.attr("data-value", dec);
			var minute = parseInt($clock.find(".shift-clock-m").val(), 10);
			$clock.find(".shift-clock-quick button").each(function () {
				$(this).toggleClass("is-on", parseInt($(this).data("min"), 10) === minute);
			});
			return dec;
		},
		value: function ($clock) {
			return this.sync($clock);
		},
		label: function ($clock) {
			return this.labelFromDecimal(this.sync($clock));
		}
	};
	$(document).off("click.shiftMer").on("click.shiftMer", ".shift-clock-mer button", function () {
		var $btn = $(this);
		$btn.addClass("is-on").siblings().removeClass("is-on");
		window.shiftClock.sync($btn.closest(".shift-clock"));
	});
	$(document).off("change.shiftClock").on("change.shiftClock", ".shift-clock-h, .shift-clock-m", function () {
		window.shiftClock.sync($(this).closest(".shift-clock"));
	});
	$(document).off("click.shiftQuick").on("click.shiftQuick", ".shift-clock-quick button", function () {
		var $clock = $(this).closest(".shift-clock");
		$clock.find(".shift-clock-m").val(String($(this).data("min")));
		window.shiftClock.sync($clock);
	});
</script>
