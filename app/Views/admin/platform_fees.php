<div class="app-inner-layout app-inner-layout-page">
	<div class="app-inner-layout__wrapper">
		<div class="app-inner-layout__content">
			<div class="tab-content">
				<div class="container-fluid">
					<div class="card mb-3">
						<div class="card-header-tab card-header">
							<div class="card-header-title font-size-lg text-capitalize font-weight-normal">
								<i class="header-icon typcn typcn-vendor-microsoft text-muted opacity-6"></i>
								<?= esc($title ?? 'Registration fees'); ?>
							</div>
						</div>
						<div class="card-body">
							<form id="platformFeesForm" style="max-width:420px;">
								<div class="form-group">
									<label>Service fee (RWF)</label>
									<input type="number" min="0" step="1" class="form-control" name="service_fee" id="service_fee"
										   value="<?= (int) ($fees['service_fee'] ?? 0); ?>" required>
								</div>
								<div class="form-group">
									<label>Platform fee (RWF)</label>
									<input type="number" min="0" step="1" class="form-control" name="platform_fee" id="platform_fee"
										   value="<?= (int) ($fees['platform_fee'] ?? 0); ?>">
								</div>
								<button type="submit" class="btn btn-primary" id="btnSavePlatformFees">
									<i class="fa fa-save"></i> Save fees
								</button>
								<span id="platformFeesStatus" class="text-muted" style="margin-left:.75rem;"></span>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
<script>
$(function () {
	$("#platformFeesForm").on("submit", function (e) {
		e.preventDefault();
		var $st = $("#platformFeesStatus").text("Saving…");
		$.post("<?= base_url('admin/save_platform_fees'); ?>", {
			service_fee: $("#service_fee").val(),
			platform_fee: $("#platform_fee").val()
		}, function (data) {
			if (data && data.success) {
				$st.text(data.success);
				if (data.fees) {
					$("#service_fee").val(data.fees.service_fee);
					$("#platform_fee").val(data.fees.platform_fee);
				}
				if (window.toastada) toastada.success(data.success);
			} else {
				$st.text((data && data.error) || "Save failed");
				if (window.toastada) toastada.error((data && data.error) || "Save failed");
			}
		}, "json").fail(function (xhr) {
			var msg = (xhr.responseJSON && xhr.responseJSON.error) || "Save failed";
			$st.text(msg);
		});
	});
});
</script>
