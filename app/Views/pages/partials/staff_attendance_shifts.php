<style>
	.hours {
		border: 1px solid #4ec4b3;
		margin: 5px;
		padding: 5px;
		border-radius: 5px;
	}
	.hours .remove-hours {
		float: right;
		color: orangered;
	}
	.shift-actions label {
		margin-right: 10px;
		cursor: pointer;
	}
</style>
<div class="mb-3">
	<p class="text-muted" style="margin-bottom:1rem;">
		Define work shifts and working hours for staff attendance. Assign a shift when
		<strong>adding staff</strong> or later from <a href="<?= base_url('staffs'); ?>">View all staffs</a>.
	</p>
	<div class="text-right mb-2">
		<button type="button" class="btn btn-gradient-primary btn-sm btn-add-shift" data-toggle="modal" data-target="#mdlAddShift">
			<i class="typcn typcn-plus"></i> <?= lang("app.addNewShift"); ?>
		</button>
	</div>
	<table style="width: 100%;" class="table table-hover table-striped table-bordered dataTable dtr-inline staff-shifts-table">
		<thead>
		<tr>
			<th>#</th>
			<th><?= lang("app.title"); ?></th>
			<th><?= lang("app.days"); ?></th>
			<th><?= lang("app.staffs"); ?></th>
			<th><?= lang("app.createdTime"); ?></th>
			<th><?= lang("app.status"); ?></th>
			<th></th>
		</tr>
		</thead>
		<tbody>
		<?php
		$a = 1;
		foreach (($shifts ?? []) as $shift) {
			$status = $shift['status'] == 1
				? '<label class="text-success lnk" data-toggle="update" data-href="change_status/shift/0" data-target="' . $shift["id"] . '">' . lang("app.active") . '</label>'
				: '<label class="text-danger lnk" data-toggle="update" data-href="change_status/shift/1" data-target="' . $shift["id"] . '">' . lang("app.locked") . '</label>';
			$hours = json_decode($shift['options'], true);
			if (!is_array($hours)) {
				$hours = [];
			}
			?>
			<tr>
				<td><?= $a; ?></td>
				<td><?= esc($shift['title']); ?></td>
				<td>
					<div class="hours-view">
						<?php foreach ($hours as $hour):
							$hhh = explode(" ", $hour);
							$weekday = $hhh[0] ?? 0;
							$start = $hhh[1] ?? 0;
							$end = $hhh[2] ?? 0;
							?>
							<div class="hours" style="font-size: 9pt">
								<span class="dayy"><?= days_mini($weekday); ?></span>:
								<span class="openn"><?= hours($start); ?></span> -
								<span class="closee"><?= hours($end); ?></span>
							</div>
						<?php endforeach; ?>
					</div>
				</td>
				<td><label class="badge badge-success" style="font-size: 14pt"><?= (int)$shift['staffs']; ?></label></td>
				<td><?= esc($shift['created_at']); ?></td>
				<td><?= $status; ?></td>
				<td class="shift-actions">
					<label class="typcn typcn-edit text-primary link btn-edit-shift"
						   data-toggle="modal" data-target="#mdlAddShift"
						   data-id="<?= (int) $shift['id']; ?>"
						   data-title="<?= esc($shift['title'], 'attr'); ?>"
						   data-options="<?= esc($shift['options'], 'attr'); ?>"><?= lang("app.edit"); ?></label>
					<label class="typcn typcn-delete text-danger link" data-toggle="delete"
						   data-title="shift #<?= esc($shift['title']); ?>"
						   data-target="<?= $shift['id']; ?>" data-href="delete_shift"><?= lang("app.del"); ?></label>
				</td>
			</tr>
			<?php
			$a++;
		}
		?>
		</tbody>
	</table>
</div>
<script>
	$(function () {
		function optionText($select, value) {
			var exact = $select.find("option").filter(function () {
				return String($(this).val()) === String(value);
			}).first();
			if (exact.length) {
				return exact.text();
			}
			var num = parseFloat(value);
			var match = $select.find("option").filter(function () {
				return Math.abs(parseFloat($(this).val()) - num) < 0.001;
			}).first();
			return match.length ? match.text() : String(value);
		}

		function resetShiftModal() {
			var $m = $("#mdlAddShift");
			$m.find("#shift_edit_id").val("");
			$m.find("[name='title']").val("");
			$m.find(".hours-view").empty();
			$m.find("#mdlAddShiftTitle").text("<?= esc(lang('app.newShift'), 'js'); ?>");
		}

		function fillShiftModal(id, title, optionsRaw) {
			resetShiftModal();
			var $m = $("#mdlAddShift");
			var hours = [];
			try {
				hours = typeof optionsRaw === "string" ? JSON.parse(optionsRaw) : (optionsRaw || []);
			} catch (e) {
				hours = [];
			}
			if (!Array.isArray(hours)) {
				hours = [];
			}
			$m.find("#shift_edit_id").val(id);
			$m.find("[name='title']").val(title);
			$m.find("#mdlAddShiftTitle").text("<?= esc(lang('app.edit'), 'js'); ?> shift");
			var hourview = $m.find(".hours-view");
			var weekday = $m.find(".weekday");
			var open = $m.find(".hours-start");
			var close = $m.find(".hours-end");
			hours.forEach(function (hour) {
				var parts = String(hour).split(/\s+/);
				if (parts.length < 3) {
					return;
				}
				var dayVal = parts[0];
				var openVal = parts[1];
				var closeVal = parts[2];
				var weekdayText = optionText(weekday, dayVal);
				var openText = optionText(open, openVal);
				var closeText = optionText(close, closeVal);
				hourview.append(
					"<div class='hours'><span class='dayy'>" + weekdayText + "</span><span class='openn'>" + openText + " </span><span>-</span>" +
					"<span class='closee'> " + closeText + " </span><a href='javascript:void(0)' class='remove-hours'>Remove</a>" +
					"<input name='hours[]' value='" + dayVal + " " + openVal + " " + closeVal + "' type='hidden'> </div>"
				);
			});
		}

		$(".btn-add-shift").off("click.staffShiftAdd").on("click.staffShiftAdd", function () {
			resetShiftModal();
		});
		$(document).off("click.staffShiftEdit").on("click.staffShiftEdit", ".btn-edit-shift", function () {
			fillShiftModal($(this).data("id"), $(this).attr("data-title"), $(this).attr("data-options"));
		});

		var hourview = $("#mdlAddShift .hours-view");
		$(".addhours").off("click.staffShift").on("click.staffShift", function () {
			var wrap = $(this).closest(".add-hours");
			var weekday = wrap.find(".weekday");
			var open = wrap.find(".hours-start");
			var close = wrap.find(".hours-end");
			var weekdayVal = weekday.val();
			var weekdayText = weekday.find("option:selected").text();
			var openVal = open.val();
			var openText = open.find("option:selected").text();
			var closeVal = close.val();
			var closeText = close.find("option:selected").text();
			hourview.append(
				"<div class='hours'><span class='dayy'>" + weekdayText + "</span><span class='openn'>" + openText + " </span><span>-</span>" +
				"<span class='closee'> " + closeText + " </span><a href='javascript:void(0)' class='remove-hours'>Remove</a>" +
				"<input name='hours[]' value='" + weekdayVal + " " + openVal + " " + closeVal + "' type='hidden'> </div>"
			);
			if (weekdayVal === "6") {
				weekday.val(0);
			} else {
				weekday.val(parseInt(weekdayVal, 10) + 1);
			}
		});
		$(document).off("click.staffShiftRemove").on("click.staffShiftRemove", ".remove-hours", function () {
			$(this).closest(".hours").remove();
		});
	});
</script>
