<style>
    .tablepage {
        width: 100%;
    }

    .td_date {
        text-align: center;
        line-height: 0.7;
        padding: 5px 0;
    }

    .boxed2 {
        padding: 10px;
        border: 1px solid #333;
        border-radius: 5px;
    }

    th, td {
        min-width: 30px;
        padding: 3px 5px;
        border: 1px solid #777777;
    }

    table {
        border-collapse: collapse;
    }

    .pull-right {
        float: right
    }

    .pull-left {
        float: left;
    }

    td {
        text-align: center;
        font-size: 10pt;
    }

    .right_border {
        border-right: 2px solid #000;
    }

    .total td {
        font-weight: bold;
        background-color: #dcdcdc;
    }
</style>
<?php
if ($show_header) {
    ?>
    <div class="col-12">
        <form id="frm_report" method="get" target="_blank"
              action="<?= base_url('student_boarding_report_data/true'); ?>">
            <div class="col-md-3 pull-left">
                <label><?= lang("app.date"); ?> :</label>
                <input type="date" placeholder="<?= lang("app.startDate"); ?> " class="form-control" id="date1"
                       name="date1">
            </div>
            <div class="col-md-3 pull-left">
                <label><?= lang("app.gender"); ?> :</label>
                <select class="form-control" id="gender_filter" name="gender_filter">
                    <option value=""><?= lang("app.all"); ?></option>
                    <option value="M"><?= lang("app.male"); ?></option>
                    <option value="F"><?= lang("app.female"); ?></option>
                </select>
            </div>
            <div class="col-md-3 pull-left" style="margin-top: 30px;">
                <button class="btn btn-secondary" id="btn_generate"><?= lang("app.generate"); ?> </button>
                <button class="btn btn-primary" type="submit"><i class="fa fa-file-pdf"></i> <?= lang("app.export"); ?>
                </button>
            </div>
        </form>
    </div>
    <div id="report_content"></div>
    <script>
        $(function () {
            $("#select_course").on("change", function (e) {
                var val = $(this).val();
                $("#select_class").load("<?= base_url(); ?>get_class/" + val);
            });
            $("#btn_generate").on("click", function (e) {
                e.preventDefault();
                if ($("#date1").val() == "") {
                    toastada.warning('<?= lang("app.strtDateErr"); ?>');
                    return;
                }
                $("#btn_generate").text('<?= lang("app.pleaseWait"); ?>').prop("disabled", true);
                $("#report_content").load("<?=base_url('student_boarding_report_data');?>", $("#frm_report").serialize(), function () {
                    $("#btn_generate").text('<?= lang("app.generate"); ?>').prop("disabled", false);
                })
            });
        });
    </script>
    <?php
} else {
    $gender_filter = isset($_GET['gender_filter']) ? $_GET['gender_filter'] : '';
    
    if (count($classes) == 0) {
        echo "<h4 style='width: 100%;float: left;text-align: center;margin-top: 15px'>" . lang("app.noBoardingStudents") . "</h4>";
        die();
    }
    ?>
    <div style="margin-top: 15px;width: 100%;float:left;" id="printable">
        <div class="col-md-12 col-sm-12 pull-left" style="margin-bottom: 15px">
            <div style="background:white;padding: 10px;overflow: auto;">
                <?php if ($pdf) { ?>
                    <div class="col-sm-12">
                        <div class="col-md-6 pull-left">
                            <span><b><?= lang("app.republic"); ?> </b></span><br>
                            <span><b><?= lang("app.ministry"); ?> </b></span><br>
                            <span><strong><?= $school_name; ?></strong></span><br>
                            <span><b><?= lang("app.mail"); ?></b> : <?= $school_email; ?></span><br>
                            <span><b><?= lang("app.phone"); ?> </b>  : <?= $school_phone; ?></span><br>
                        </div>
                        <div class="pull-right" style="margin-top: 10px;margin-right: 10px">
                            <div>
                                <img src="<?= base_url('assets/images/logo/' . $school_logo); ?>"
                                     style="width: 110px"><br>
                            </div>
                        </div>
                        <br>
                        <h4 style="text-decoration: underline;width: 100%;float: left;text-align: center;"><?= lang("app.boardingStudentAttendance"); ?> </h4>
                    </div>
                <?php } ?>
                <div class="col-sm-12">
                    <div class="col-md-6 pull-left">
                        <span><b><?= lang("app.date"); ?>  </b> : <?= $date1; ?></span><br>
                        <?php if ($gender_filter != '') { ?>
                            <span><b><?= lang("app.gender"); ?>  </b> : <?= $gender_filter == 'M' ? lang("app.male") : lang("app.female"); ?></span><br>
                        <?php } ?>
                    </div>
                    <div class="pull-right" style="margin-top: 10px;margin-right: 10px">
                        <span><b><?= lang("app.printedOn"); ?>  </b> : <?= date("Y-m-d H:i"); ?></span><br>
                    </div>
                </div>
                <table class="tablepage" border="1">
                    <tbody id="disciplineTable">
                    <tr>
                        <td class="right_border" style="min-width: 80px"><strong><?= lang("app.sClass"); ?> </strong></td>
                        <td colspan="3"><strong><?= lang("app.registered"); ?> </strong></td>
                        <td colspan="3"><strong><?= lang("app.present"); ?> </strong></td>
                        <td colspan="3" class="right_border"><strong><?= lang("app.absents"); ?> </strong></td>
                    </tr>
                    <tr>
                        <td class="right_border"></td>
                        <?php if ($gender_filter == '') { ?>
                            <td><strong><?= lang("app.male"); ?> </strong></td>
                            <td><strong><?= lang("app.female"); ?> </strong></td>
                            <td><strong><?= lang("app.tot"); ?> </strong></td>
                            <td><strong><?= lang("app.male"); ?> </strong></td>
                            <td><strong><?= lang("app.female"); ?> </strong></td>
                            <td><strong><?= lang("app.tot"); ?> </strong></td>
                            <td><strong><?= lang("app.male"); ?> </strong></td>
                            <td><strong><?= lang("app.female"); ?> </strong></td>
                            <td class="right_border"><strong><?= lang("app.tot"); ?> </strong></td>
                        <?php } else { ?>
                            <td colspan="3"><strong><?= $gender_filter == 'M' ? lang("app.male") : lang("app.female"); ?> </strong></td>
                            <td colspan="3"><strong><?= $gender_filter == 'M' ? lang("app.male") : lang("app.female"); ?> </strong></td>
                            <td colspan="3" class="right_border"><strong><?= $gender_filter == 'M' ? lang("app.male") : lang("app.female"); ?> </strong></td>
                        <?php } ?>
                    </tr>
                    <?php
                    $male_registered_tot = 0;
                    $female_registered_tot = 0;
                    $male_present_tot = 0;
                    $female_present_tot = 0;

                    foreach ($classes as $item) {
                        $male_registered = 0;
                        $female_registered = 0;
                        $male_present = 0;
                        $female_present = 0;

                        $dailyMdl = new \App\Models\BoardingAttendanceModel();
                        $stMdl = new \App\Models\StudentModel();

                        // Registered students
                        $studentsQuery = $stMdl->select("students.sex, count(students.id) as count")
                            ->join("class_records cr", "cr.student=students.id")
                            ->where("cr.class", $item['id'])
                            ->where("cr.year", $academic_year)
                            ->where("school_id", $_SESSION["soma_school_id"])
                            ->where("studying_mode", 0);
                        if ($gender_filter != '') {
                            $studentsQuery->where("students.sex", $gender_filter);
                        }
                        $students = $studentsQuery->groupBy("students.sex")->get()->getResultArray();
                        foreach ($students as $student) {
                            if ($student['sex'] == "M") {
                                $male_registered = $student['count'];
                                $male_registered_tot += $student['count'];
                            } else {
                                $female_registered = $student['count'];
                                $female_registered_tot += $student['count'];
                            }
                        }

                        // Present students (first clock only)
                        $presentQuery = $dailyMdl->select("st.sex, COUNT(DISTINCT st.id) as count")
                            ->join("students st", "st.id=boarding_attendance.student_id")
                            ->join("class_records cr", "cr.student=st.id")
                            ->where("cr.class", $item['id'])
                            ->where("cr.year", $academic_year)
                            ->where("boarding_attendance.datee", $date1)
                            ->where("school_id", $_SESSION["soma_school_id"])
                            ->where("st.studying_mode", 0);
                        if ($gender_filter != '') {
                            $presentQuery->where("st.sex", $gender_filter);
                        }
                        $present_records = $presentQuery->groupBy("st.sex")->get()->getResultArray();
                        foreach ($present_records as $record) {
                            if ($record['sex'] == "M") {
                                $male_present = $record['count'];
                                $male_present_tot += $record['count'];
                            } else {
                                $female_present = $record['count'];
                                $female_present_tot += $record['count'];
                            }
                        }
                        ?>
                        <tr>
                            <td class="right_border"><?= $item['level_name'] . ' ' . $item['code'] . ' ' . $item['title']; ?></td>
                            <?php if ($gender_filter == '') { ?>
                                <td><?= $male_registered; ?></td>
                                <td><?= $female_registered; ?></td>
                                <td><?= $male_registered + $female_registered; ?></td>
                                <td><?= $male_present; ?></td>
                                <td><?= $female_present; ?></td>
                                <td><?= $male_present + $female_present; ?></td>
                                <td><?= $male_registered - $male_present; ?></td>
                                <td><?= $female_registered - $female_present; ?></td>
                                <td class="right_border"><?= ($male_registered + $female_registered) - ($male_present + $female_present); ?></td>
                            <?php } else {
                                $registered = $gender_filter == 'M' ? $male_registered : $female_registered;
                                $present = $gender_filter == 'M' ? $male_present : $female_present;
                                ?>
                                <td colspan="3"><?= $registered; ?></td>
                                <td colspan="3"><?= $present; ?></td>
                                <td colspan="3" class="right_border"><?= $registered - $present; ?></td>
                            <?php } ?>
                        </tr>
                        <?php
                    }
                    ?>
                    <tr class="total">
                        <td class="right_border"><?= lang("app.total"); ?> </td>
                        <?php if ($gender_filter == '') { ?>
                            <td><?= $male_registered_tot; ?></td>
                            <td><?= $female_registered_tot; ?></td>
                            <td><?= $male_registered_tot + $female_registered_tot; ?></td>
                            <td><?= $male_present_tot; ?></td>
                            <td><?= $female_present_tot; ?></td>
                            <td><?= $male_present_tot + $female_present_tot; ?></td>
                            <td><?= $male_registered_tot - $male_present_tot; ?></td>
                            <td><?= $female_registered_tot - $female_present_tot; ?></td>
                            <td class="right_border"><?= ($male_registered_tot + $female_registered_tot) - ($male_present_tot + $female_present_tot); ?></td>
                        <?php } else {
                            $total_registered = $gender_filter == 'M' ? $male_registered_tot : $female_registered_tot;
                            $total_present = $gender_filter == 'M' ? $male_present_tot : $female_present_tot;
                            ?>
                            <td colspan="3"><?= $total_registered; ?></td>
                            <td colspan="3"><?= $total_present; ?></td>
                            <td colspan="3" class="right_border"><?= $total_registered - $total_present; ?></td>
                        <?php } ?>
                    </tr>
                    </tbody>
                </table>
                <div class="col-md-12 col-sm-12 ">
                    <footer class="pull-right" style="color: darkgrey"><?= lang("app.generatedbySomanet"); ?> </footer>
                </div>
            </div>
        </div>
    </div>
    <?php
}
?>
