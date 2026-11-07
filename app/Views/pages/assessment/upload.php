<?php
$uploads = $uploads ?? [];
$assignments = $assignments ?? [];
$teachers = $teachers ?? [];
$viewAll = !empty($view_all_assessments);
$filterTeacher = (int) ($filter_teacher ?? 0);
$myId = (int) ($_SESSION['soma_id'] ?? 0);
?>
<div class="card mb-3">
	<div class="card-body">
		<h4 class="card-title mb-1">Assessment upload</h4>
		<p class="text-muted mb-3">
			<?php if ($viewAll) { ?>
				Type the assessment and upload a PDF or Word file. You can open every teacher's assessment in this school. A teacher can open only the files they uploaded.
			<?php } else { ?>
				Select your class and course, type the assessment, and upload a PDF or Word file. Only you, the Head Teacher, and the Director can open it. Other teachers cannot.
			<?php } ?>
		</p>
		<?php if (session()->getFlashdata('success')) { ?>
			<div class="alert alert-success"><?= esc(session()->getFlashdata('success')); ?></div>
		<?php } ?>
		<?php if (session()->getFlashdata('error')) { ?>
			<div class="alert alert-danger"><?= esc(session()->getFlashdata('error')); ?></div>
		<?php } ?>

		<form method="post" action="<?= base_url('assessmentupload/save'); ?>" enctype="multipart/form-data">
			<div class="form-row">
				<?php if ($assignments !== []) { ?>
				<div class="form-group col-md-4">
					<label for="assignment">Class and course</label>
					<select class="form-control" id="assignment" name="assignment" required>
						<option value="">Select class and course</option>
						<?php foreach ($assignments as $item) { ?>
							<option value="<?= (int) $item['class_id']; ?>:<?= (int) $item['course_id']; ?>">
								<?= esc($item['class_title'] . ' — ' . $item['course_title']); ?>
							</option>
						<?php } ?>
					</select>
				</div>
				<?php } ?>
				<div class="form-group col-md-4">
					<label for="assessment_type">Assessment type</label>
					<input class="form-control" id="assessment_type" name="assessment_type" list="assessment-types" maxlength="80" required placeholder="Type it, for example Mid-term or Quiz">
					<datalist id="assessment-types">
						<option value="Quiz"></option>
						<option value="Test"></option>
						<option value="Homework"></option>
						<option value="CAT"></option>
						<option value="Mid-term"></option>
						<option value="End of term"></option>
						<option value="Exam"></option>
					</datalist>
				</div>
				<div class="form-group col-md-4">
					<label for="assessment_file">PDF or Word file</label>
					<input class="form-control" id="assessment_file" type="file" name="assessment_file" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required>
				</div>
			</div>
			<button type="submit" class="btn btn-primary">Upload assessment</button>
		</form>
	</div>
</div>

<div class="card">
	<div class="card-body">
		<div class="d-flex justify-content-between align-items-center mb-3">
			<h5 class="mb-0"><?= $viewAll ? 'All teachers' : 'My assessments'; ?></h5>
			<?php if ($viewAll && $teachers !== []) { ?>
				<form method="get" action="<?= base_url('assessmentupload'); ?>" class="form-inline">
					<label class="mr-2 mb-0" for="teacher">Teacher</label>
					<select class="form-control" id="teacher" name="teacher" onchange="this.form.submit()">
						<option value="0">All teachers</option>
						<?php foreach ($teachers as $teacher) {
							$id = (int) ($teacher['staff_id'] ?? 0);
							$name = trim((string) ($teacher['fname'] ?? '') . ' ' . (string) ($teacher['lname'] ?? ''));
							?>
							<option value="<?= $id; ?>" <?= $filterTeacher === $id ? 'selected' : ''; ?>><?= esc($name !== '' ? $name : ('Staff ' . $id)); ?></option>
						<?php } ?>
					</select>
				</form>
			<?php } ?>
		</div>
		<div class="table-responsive">
			<table class="table table-bordered table-striped mb-0">
				<thead>
					<tr>
						<?php if ($viewAll) { ?><th>Teacher</th><?php } ?>
						<th>Class</th>
						<th>Course</th>
						<th>Assessment type</th>
						<th>File</th>
						<th>Uploaded</th>
						<th></th>
					</tr>
				</thead>
				<tbody>
					<?php if ($uploads === []) { ?>
						<tr>
							<td colspan="<?= $viewAll ? 7 : 6; ?>" class="text-muted">No assessment has been uploaded yet.</td>
						</tr>
					<?php } ?>
					<?php foreach ($uploads as $row) {
						$name = trim((string) ($row['fname'] ?? '') . ' ' . (string) ($row['lname'] ?? ''));
						$mine = (int) ($row['staff_id'] ?? 0) === $myId;
						?>
						<tr>
							<?php if ($viewAll) { ?><td><?= esc($name !== '' ? $name : 'Teacher'); ?></td><?php } ?>
							<td><?= esc((string) ($row['class_title'] ?? '')); ?></td>
							<td><?= esc((string) ($row['course_title'] ?? '')); ?></td>
							<td><?= esc((string) ($row['assessment_type'] ?? '')); ?></td>
							<td><?= esc((string) ($row['original_name'] ?? '')); ?></td>
							<td><?= esc((string) ($row['created_at'] ?? '')); ?></td>
							<td class="text-nowrap">
								<a class="btn btn-sm btn-outline-primary" href="<?= base_url('assessmentupload/file/' . (int) $row['id']); ?>">Download</a>
								<?php if ($mine) { ?>
									<form method="post" action="<?= base_url('assessmentupload/remove/' . (int) $row['id']); ?>" class="d-inline" onsubmit="return confirm('Remove this assessment?');">
										<button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
									</form>
								<?php } ?>
							</td>
						</tr>
					<?php } ?>
				</tbody>
			</table>
		</div>
	</div>
</div>
