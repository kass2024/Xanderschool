<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Print Permission | <?= strtoupper($school_name); ?></title>

<style>
/* ---------- GLOBAL STYLES ---------- */
body {
  font-family: "Poppins", Arial, sans-serif;
  background: #f2f3f7;
  color: #333;
  margin: 0;
  padding: 0;
}

/* ---------- PAGE HEADER ---------- */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: #fff;
  border-bottom: 2px solid #dee2e6;
  padding: 12px 25px;
  box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.header-left {
  flex: 1;
}
.page-title {
  font-size: 18px;
  font-weight: 600;
  color: #222;
  margin: 0;
}
.subtitle {
  font-size: 13px;
  color: #6c757d;
  margin: 2px 0 0;
}

.header-center {
  text-align: center;
  flex: 1.5;
}
.school-name {
  font-size: 20px;
  font-weight: 700;
  color: #0b2239;
  text-transform: uppercase;
  margin: 0;
  letter-spacing: 0.6px;
}
.academic-year {
  font-size: 14px;
  color: #495057;
  margin-top: 3px;
  font-weight: 500;
}

.header-right {
  flex: 1;
  display: flex;
  justify-content: flex-end;
  align-items: center;
}
.icons i {
  font-size: 17px;
  margin-left: 18px;
  color: #444;
  cursor: pointer;
  transition: color .2s ease;
}
.icons i:hover {
  color: #007bff;
}

/* ---------- MAIN BODY ---------- */
.main-content {
  display: flex;
  justify-content: center;
  align-items: flex-start;
  padding: 30px;
}

/* ---------- PERMISSION SLIP ---------- */
.ticket {
  width: 85mm;
  max-width: 400px;
  background: #fff;
  border: 1px solid #ddd;
  border-radius: 10px;
  padding: 15px 12px;
  box-shadow: 0 2px 10px rgba(0,0,0,0.1);
  text-align: center;
}

.print-header {
  font-size: 16px;
  font-weight: 700;
  text-transform: uppercase;
  color: #222;
  margin-bottom: 10px;
  border-bottom: 2px solid #333;
  padding-bottom: 5px;
  letter-spacing: .5px;
}

.title {
  font-weight: 600;
  text-transform: uppercase;
  font-size: 12px;
  line-height: 1.5;
  margin-bottom: 6px;
  color: #444;
}

.logo img {
  width: 50px;
  height: auto;
  margin: 5px 0;
}

.school-name-inner {
  font-size: 13px;
  font-weight: 700;
  text-transform: uppercase;
  color: #111;
  margin-top: 4px;
}

.school-info {
  font-size: 11px;
  line-height: 1.4;
  color: #555;
  margin-top: 3px;
  margin-bottom: 6px;
}

hr {
  border: 0;
  border-top: 1px dashed #999;
  margin: 8px 0;
}

.section {
  text-align: left;
  padding: 3px 5px;
  font-size: 12px;
  line-height: 1.5;
}

.label {
  font-weight: 600;
  color: #222;
}

.footer {
  text-align: center;
  margin-top: 10px;
  font-size: 11px;
  font-style: italic;
  color: #333;
}

/* ---------- PRINT BUTTON ---------- */
#printBtn {
  display: inline-block;
  margin: 18px auto;
  background: #007bff;
  color: #fff;
  border: none;
  padding: 8px 16px;
  border-radius: 6px;
  font-size: 13px;
  cursor: pointer;
  transition: background 0.3s ease;
}
#printBtn:hover {
  background: #0056b3;
}

/* ---------- PRINT STYLES ---------- */
@media print {
  @page {
    size: 80mm auto;
    margin: 3mm;
  }
  body, html {
    background: #fff;
    margin: 0;
    padding: 0;
  }
  body * {
    visibility: hidden;
  }
  #printable, #printable * {
    visibility: visible;
  }
  #printable {
    position: absolute;
    left: 0;
    right: 0;
    top: 0;
    margin: auto;
  }
  #printBtn {
    display: none !important;
  }
  .ticket {
    box-shadow: none;
    border: none;
  }
  .page-header {
    display: none !important;
  }
}

/* ---------- RESPONSIVE ---------- */
@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    gap: 6px;
    text-align: center;
  }
  .header-right {
    justify-content: center;
  }
}
/* ---- Signature Styling ---- */
.signature-block {
  text-align: center;
  margin-top: 10px;
  position: relative;
  display: inline-block;
}

.signature-img {
  width: 155px;
  height: 160px;
  opacity: 0.9;
  filter: drop-shadow(0 0 2px rgba(0,0,0,0.2));
  margin-bottom: 4px;
}

.sign-label {
  font-size: 11px;
  font-weight: 600;
  color: #222;
  margin-top: 3px;
}

.stamp {
  position: absolute;
  top: -10px;
  right: -20px;
  background: rgba(255,0,0,0.1);
  color: red;
  font-weight: 700;
  font-size: 11px;
  padding: 2px 6px;
  border: 1px solid red;
  border-radius: 4px;
  transform: rotate(-10deg);
  letter-spacing: 1px;
}

</style>
<!-- For icons (optional) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>


<!-- MAIN PERMISSION CONTENT -->
<div class="main-content">
  <div class="ticket" id="printable">
    <div class="print-header">Student Permission Slip</div>

    <div class="title">
      Republic of Rwanda<br>
      Ministry of Education<br>
      Student’s Leave Permission
    </div>

    <div class="logo">
      <?php if (!empty($school_logo)) : ?>
        <img src="<?= base_url('assets/images/logo/' . $school_logo); ?>" alt="School Logo">
      <?php endif; ?>
    </div>

    <div class="school-name-inner"><?= strtoupper($school_name); ?></div>
    <div class="school-info">
      <b>Email:</b> <?= $school_email; ?><br>
      <b>Phone:</b> <?= $school_phone; ?>
    </div>

    <hr>
    <div class="section">
      <span class="label">First Name:</span> <?= $permissions['fname']; ?><br>
      <span class="label">Last Name:</span> <?= $permissions['lname']; ?><br>
      <span class="label">Class:</span> <?= $permissions['level_name'] . ' ' . $permissions['title'] . ' ' . $permissions['code']; ?><br>
      <span class="label">Registration No:</span> <?= $permissions['regno']; ?>
    </div>

    <hr>
    <div class="section">
      <span class="label">Destination:</span> <?= $permissions['destination']; ?><br>
      <span class="label">Reason:</span> <?= $permissions['reason']; ?><br>
      <span class="label">Leave Time:</span> <?= $permissions['leave_time']; ?><br>
      <span class="label">Return Time:</span> <?= $permissions['return_time']; ?>
    </div>

    <hr>
 <div class="footer">
  Done at <b><?= strtoupper($school_name); ?></b><br>
  On <?= date('d/m/Y H:i'); ?><br>

  <?php
    $post = strtolower(session('soma_post_title'));
    $signature_path = '';

    // Match logged-in staff to their saved signature
    if ($post == 'head master' || $post == 'headmistress' || $post == 'principal') {
        $signature_path = $settings['headmaster_signature'];
    } elseif ($post == 'patron') {
        $signature_path = $settings['patron_signature'];
    } elseif ($post == 'matron') {
        $signature_path = $settings['matron_signature'];
    } elseif ($post == 'dean of discipline' || $post == 'head of discipline') {
        $signature_path = $settings['discipline_signature'];
    }

    if (!empty($signature_path)) {
        echo '<div class="signature-block">';
        echo '<img src="'.base_url('assets/images/signatures/'.$signature_path).'" alt="Signature" class="signature-img">';
        echo '<div class="stamp">APPROVED</div>';
        echo '<div class="sign-label"><b>'.session('soma_name').' — '.session('soma_post_title').'</b></div>';
        echo '</div>';
    } else {
        echo '<b>'.session('soma_name').' — '.session('soma_post_title').'</b>';
    }
  ?>
</div>

  </div>
</div>

<!-- PRINT BUTTON -->
<div style="text-align:center;">
  <button id="printBtn" onclick="window.print()">Print to PDF / Thermal</button>
</div>

</body>
</html>
