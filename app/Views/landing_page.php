<!doctype html>
<html lang="en">
<head>
  <title>IOTXAD</title>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,400,700" rel="stylesheet">
  <link rel="stylesheet" href="fonts/icomoon/style.css">

  <link rel="stylesheet" href="<?= base_url(); ?>assets/landing/css/bootstrap.min.css">
  <link rel="stylesheet" href="<?= base_url(); ?>assets/landing/css/jquery-ui.css">
  <link rel="stylesheet" href="<?= base_url(); ?>assets/landing/css/owl.carousel.min.css">
  <link rel="stylesheet" href="<?= base_url(); ?>assets/landing/css/owl.theme.default.min.css">
  <link rel="stylesheet" href="<?= base_url(); ?>assets/landing/css/jquery.fancybox.min.css">
  <link rel="stylesheet" href="<?= base_url(); ?>assets/landing/css/bootstrap-datepicker.css">
  <link rel="stylesheet" href="fonts/flaticon/font/flaticon.css">
  <link rel="stylesheet" href="<?= base_url(); ?>assets/landing/css/aos.css">
  <link rel="stylesheet" href="<?= base_url(); ?>assets/landing/css/style.css">

  <!-- Favicon -->
  <link rel="favorite icon" href="<?= base_url(); ?>assets/landing/images/about.png">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.0.3/css/font-awesome.css">

  <style>
    /* ----- minor visual helpers from your original ----- */
    #serv1:hover,#serv2:hover,#serv3:hover,#serv4:hover,#serv5:hover,#serv6:hover,#serv7:hover,#serv8:hover,#serv9:hover{
      box-shadow:0 0 .2em .2em #8c0000;border-radius:.4em
    }
    #mobi_bg{background-image:url('<?=base_url();?>assets/landing/soma_pics/mobi_bg2.jpg');background-size:35em;border-radius:2em}
    #opac{background-color:skyblue;opacity:.8;height:10em;border-radius:2em;width:103%;margin-left:-1.5%}
    #down:hover{background-color:blue}
    .sticky-wrapper.is-sticky .site-navbar .lang_switcher{color:#333!important}

    /* ----- mobile drawer + backdrop ----- */
    .mobile-backdrop{
      position:fixed;inset:0;background:rgba(15,23,42,.45);
      opacity:0;pointer-events:none;transition:opacity .25s ease;z-index:9997;
    }
    .mobile-backdrop.open{opacity:1;pointer-events:auto}
    .site-mobile-menu{
      position:fixed;top:0;bottom:0;left:0;width:86%;max-width:360px;background:#fff;
      transform:translateX(-105%);transition:transform .28s ease;z-index:9998;
      box-shadow:0 10px 40px rgba(0,0,0,.2);display:flex;flex-direction:column
    }
    .site-mobile-menu.open{transform:translateX(0)}
    .site-mobile-menu-header{padding:18px 16px;border-bottom:1px solid #e5e7eb;display:flex;align-items:center;justify-content:space-between}
    .site-mobile-menu-title{font-weight:700;margin:0;font-size:1.05rem}
    .site-mobile-menu-close{cursor:pointer;font-size:1.4rem;line-height:1;color:#111;background:transparent;border:0}
    .site-mobile-menu-body{overflow:auto;padding:8px 0;flex:1}
    .mobile-nav{list-style:none;margin:0;padding:0}
    .mobile-nav li a{display:block;padding:14px 18px;font-size:1.05rem;color:#111;text-decoration:none}
    .mobile-nav li a:hover,.mobile-nav li a:focus{background:#f3f4f6}
    .mobile-lang{padding:8px 16px;border-top:1px solid #e5e7eb}
    .mobile-lang a{margin-right:14px;text-decoration:none}
    .mobile-trigger{position:absolute;top:8px;right:12px;z-index:9999}
    body.nav-open{overflow:hidden}
    @media (min-width:1200px){ .mobile-backdrop,.site-mobile-menu{display:none} }

    /* header z-index so it stays above content when sticky */
    .site-navbar{z-index:1000;}
  </style>
</head>

<body data-spy="scroll" data-target=".site-navbar-target" data-offset="80">
<div id="overlayer" style="<?=empty($type)?'':'display:none';?>"></div>
<div class="loader" style="<?=empty($type)?'':'display:none';?>">
  <div class="spinner-border text-primary" role="status"><span class="sr-only">Loading...</span></div>
</div>

<div class="site-wrap">

  <!-- Backdrop for mobile -->
  <div class="mobile-backdrop" id="mobileBackdrop" aria-hidden="true"></div>

  <!-- Mobile Drawer -->
  <div class="site-mobile-menu site-navbar-target" id="mobileMenu" aria-label="Mobile menu" aria-hidden="true">
    <div class="site-mobile-menu-header">
      <h3 class="site-mobile-menu-title">Menu</h3>
      <button class="site-mobile-menu-close" id="mobileClose" aria-label="Close menu" type="button">
        <span class="icon-close2"></span>
      </button>
    </div>
    <div class="site-mobile-menu-body" id="mobileMenuBody"><!-- populated by JS --></div>
    <div class="mobile-lang">
      <a href="javascript:void" class="lang_switcher" data-target="en"><img src="<?=base_url('assets/images/en-flag.png');?>" width="20" height="20"> En</a>
      <a href="javascript:void" class="lang_switcher" data-target="fr"><img src="<?=base_url('assets/images/fr-flag.png');?>" width="25" height="25"> Fr</a>
    </div>
  </div>

  <?php if (empty($type)){ ?>
  <!-- ===== Header / Desktop Nav ===== -->
  <header class="site-navbar js-sticky-header site-navbar-target" role="banner">
    <div class="container">
      <div class="row align-items-center">

        <div class="col-10 col-xl-2">
          <img src="<?=base_url('assets/images/Logo1.jpg');?>" style="max-height:54px" alt="IOTXAD">
        </div>

        <div class="col-10 col-md-10 d-none d-xl-block">
          <nav class="site-navigation position-relative text-right" role="navigation">
            <ul class="site-menu main-menu js-clone-nav mr-auto d-none d-lg-block" id="desktopMenu">
              <li><a href="#home-section" class="nav-link"><?= lang("app.home");?></a></li>
              <li><a href="<?=base_url('student-marks');?>" class="nav-link"><?= lang("app.studentMarks");?></a></li>
              <li><a href="<?=base_url('application');?>" class="nav-link"><?= lang("app.onlineRegistration");?></a></li>
              <li><a href="#about-section" class="nav-link"><?= lang("app.about");?></a></li>
              <li><a href="#services-section" class="nav-link"><?= lang("app.features");?></a></li>
              <li><a href="#contact-section" class="nav-link"><?= lang("app.contact");?></a></li>
              <li><a href="<?= base_url('login'); ?>" class="nav-link"><?= lang("app.login"); ?></a></li>
            </ul>
            <div class="lang" style="position:fixed;right:20px;top:20px;">
              <a style="color:#fefefe;" href="javascript:void" class="lang_switcher" data-target="en"><img src="<?=base_url('assets/images/en-flag.png');?>" width="20" height="20">En</a>
              <a style="color:#fefefe;" href="javascript:void" class="lang_switcher" data-target="fr"><img src="<?=base_url('assets/images/fr-flag.png');?>" width="25" height="25">Fr</a>
            </div>
          </nav>
        </div>

        <!-- Mobile trigger -->
        <div class="col-12 d-inline-block d-xl-none ml-md-0 py-3 mobile-trigger" style="position:absolute;top:0;">
          <a href="#" class="site-menu-toggle js-menu-toggle float-right" id="mobileOpen" aria-label="Open menu" aria-haspopup="true" aria-controls="mobileMenu">
            <span class="fa fa-bars"></span>
          </a>
        </div>

      </div>
    </div>
  </header>
  <?php } ?>

  <!-- ===== PAGE CONTENT (your sections must exist with these IDs) ===== -->
  <?=$content;?>

  <?php if (empty($type)){ ?>
  <!-- ===== Footer ===== -->
  <footer class="site-footer">
    <div class="container-fluid">
      <div class="row">
        <div class="col-md-9">
          <div class="row">
            <div class="col-md-5">
              <h2 class="footer-heading mb-4"><?= lang("app.aboutUs");?></h2>
              <p><b>IOTXAD</b> is a platform for your school information management. It’s a complete
                packaged solution. It is flexible, you can still choose features you want to use and
                leave out what you don’t need.</p>
            </div>
            <div class="col-md-3 ml-auto">
              <h2 class="footer-heading mb-4"><?= lang("app.links");?></h2>
              <ul class="list-unstyled">
                <li><a href="#about-section" class="smoothscroll"><?= lang("app.aboutUs");?></a></li>
                <li><a href="#services-section" class="smoothscroll"><?= lang("app.features");?></a></li>
                <li><a href="#contact-section" class="smoothscroll"><?= lang("app.contactUs");?></a></li>
              </ul>
            </div>
            <div class="col-md-3 footer-social">
              <h2 class="footer-heading mb-4"><?= lang("app.followUs");?></h2>
              <a href="#" class="pl-0 pr-3"><span class="icon-facebook"></span></a>
              <a href="#" class="pl-3 pr-3"><span class="icon-twitter"></span></a>
              <a href="#" class="pl-3 pr-3"><span class="icon-instagram"></span></a>
              <a href="#" class="pl-3 pr-3"><span class="icon-linkedin"></span></a>
            </div>
          </div>
        </div>
      </div>

      <div class="row pt-5 mt-5 text-center">
        <div class="col-md-12">
          <div class="border-top pt-5">
            <p class="copyright"><small>
              Copyright &copy;<script>document.write(new Date().getFullYear());</script>
              All rights reserved | Powered by <a href="http://www.bbdigitech.com">IOTXAD Systems Ltd</a>
            </small></p>
          </div>
        </div>
      </div>
    </div>
  </footer>

  <!-- ===== Scripts ===== -->
  <script src="<?= base_url(); ?>assets/landing/js/jquery-3.3.1.min.js"></script>
  <script src="<?= base_url(); ?>assets/landing/js/jquery-ui.js"></script>
  <script src="<?= base_url(); ?>assets/landing/js/popper.min.js"></script>
  <script src="<?= base_url(); ?>assets/landing/js/bootstrap.min.js"></script>
  <script src="<?= base_url(); ?>assets/landing/js/owl.carousel.min.js"></script>
  <script src="<?= base_url(); ?>assets/landing/js/jquery.countdown.min.js"></script>
  <script src="<?= base_url(); ?>assets/landing/js/jquery.easing.1.3.js"></script>
  <script src="<?= base_url(); ?>assets/landing/js/aos.js"></script>
  <script src="<?= base_url(); ?>assets/landing/js/jquery.fancybox.min.js"></script>
  <script src="<?= base_url(); ?>assets/landing/js/jquery.sticky.js"></script>
  <script src="<?= base_url(); ?>assets/landing/js/isotope.pkgd.min.js"></script>
  <script src="<?= base_url(); ?>assets/landing/js/main.js"></script>

  <script>
    /* =========================
       Language + form handlers
       ========================= */
    $(function () {
      var active_btn = null;

      $(document).on("click",".lang_switcher",function () {
        var lang = $(this).data("target");
        $.getJSON("<?=base_url('set_lang/');?>"+lang,function (json) {
          if (json && json.success){ location.reload(); }
          else{ alert("Changing language failed"); }
        });
      });

      $(document).on("click", "form [type='submit']", function () { active_btn = $(this); });
      if ($("form").parsley) { $("form").parsley(); }

      $(".btnrecover").on("click",function () { $("#frm_reset").slideDown(300); $("#frm_login").slideUp(300); });
      $(".btnback").on("click",function () { $("#frm_reset").slideUp(300); $("#frm_login").slideDown(300); });

      $(".autoSubmit").on("submit", function (e) {
        e.preventDefault();
        var form=$(this), btn=active_btn, btn_txt=btn.text();
        btn.text("Please wait...").prop("disabled",true);
        $.post(form.prop("action"), $(this).serialize(), function (data) {
          btn.text(btn_txt).prop("disabled",false);
          if (data.error){ toastada.error(data.error); }
          else if (data.success){
            if (btn.data("target")){
              toastada.success(data.success);
              var target=btn.data("target");
              if (target.startsWith("#")){ $(target).modal('hide'); return; }
              if (target=="reload"){ setTimeout(function(){ location.reload(); },1500); return; }
              setTimeout(function(){ location.href=btn.data("target"); },1500);
            }else{ toastada.success(data.success); form.trigger("reset"); }
          }else{ toastada.error("Fatal error occurred, if the problem persist please contact system admin"); }
        }).fail(function(){ btn.text(btn_txt).prop("disabled",false); toastada.error("System server error, please try again later"); });
      });

      $('.toggle').click(function(e){ e.preventDefault(); $(this).toggleClass('toggle-on'); });
    });

    /* =========================
       Mobile drawer (clone desktop)
       ========================= */
    (function(){
      var $drawer   = $('#mobileMenu');
      var $backdrop = $('#mobileBackdrop');
      var $openBtn  = $('#mobileOpen');
      var $closeBtn = $('#mobileClose');
      var $body     = $('#mobileMenuBody');

      function buildMobileMenu(){
        $body.empty();
        var $ul = $('<ul class="mobile-nav"></ul>');
        $('#desktopMenu > li > a').each(function(){
          var href = $(this).attr('href') || '#';
          var text = $(this).text();
          $ul.append('<li><a class="mobile-link" href="'+href+'">'+text+'</a></li>');
        });
        $body.append($ul);
      }

      function openDrawer(e){
        if(e){ e.preventDefault(); }
        buildMobileMenu();
        $('body').addClass('nav-open').addClass('offcanvas-menu'); // kill template race
        $drawer.addClass('open').attr('aria-hidden','false');
        $backdrop.addClass('open').attr('aria-hidden','false');
        setTimeout(function(){ $drawer.find('a,button').first().trigger('focus'); }, 50);
      }

      function reallyHideDrawer(){
        // hard hide, even if other scripts try to keep it open
        $drawer.removeClass('open').attr('aria-hidden','true').css('transform','translateX(-105%)');
        $backdrop.removeClass('open').attr('aria-hidden','true');
      }

      function closeDrawer(){
        $('body').removeClass('nav-open offcanvas-menu');
        reallyHideDrawer();
        $openBtn.trigger('focus');
      }

      // Ensure single handler
      $(document).off('click', '.js-menu-toggle').on('click', '.js-menu-toggle', openDrawer);
      $closeBtn.on('click', closeDrawer);
      $backdrop.on('click', closeDrawer);

      // ✅ Close drawer AND smooth-scroll when tapping any in-page link in the drawer
      $(document).on('click', '#mobileMenu a', function(e){
        var href = $(this).attr('href') || '';
        // external link: just close and let browser navigate
        if (!href.startsWith('#') || href.length === 1) { closeDrawer(); return; }

        // in-page link: prevent default, close, then smooth scroll
        e.preventDefault();
        closeDrawer();

        // smooth scroll with header offset
        var HEADER_OFFSET = 80;
        var el = document.querySelector(href);
        if (!el) return;
        var rect = el.getBoundingClientRect();
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var top = rect.top + scrollTop - HEADER_OFFSET;
        setTimeout(function(){ window.scrollTo({ top: top, behavior: 'smooth' }); }, 60);
      });

      $(document).on('keydown', function(e){ if (e.key === 'Escape') closeDrawer(); });

      $(window).on('load', buildMobileMenu);
    })();

    /* =========================
       Smooth scroll (desktop + footer etc.)
       ========================= */
    (function(){
      var HEADER_OFFSET = 80; // match body data-offset
      function isHashLink(el){
        var href = el.getAttribute('href') || '';
        return href.startsWith('#') && href.length > 1;
      }
      function scrollToHash(hash){
        var el = document.querySelector(hash);
        if (!el) return;
        var rect = el.getBoundingClientRect();
        var scrollTop = window.pageYOffset || document.documentElement.scrollTop;
        var top = rect.top + scrollTop - HEADER_OFFSET;
        window.scrollTo({ top: top, behavior: 'smooth' });
      }
      document.addEventListener('click', function(e){
        var a = e.target.closest('a');
        if (!a) return;
        if (isHashLink(a)) {
          // If drawer is open for any reason, force-close before scrolling
          if (document.body.classList.contains('nav-open') || document.body.classList.contains('offcanvas-menu')) {
            document.body.classList.remove('nav-open','offcanvas-menu');
            document.getElementById('mobileMenu')?.classList.remove('open');
            document.getElementById('mobileBackdrop')?.classList.remove('open');
          }
          e.preventDefault();
          scrollToHash(a.getAttribute('href'));
        }
      });
      window.addEventListener('load', function(){
        if (window.location.hash && document.querySelector(window.location.hash)){
          setTimeout(function(){ scrollToHash(window.location.hash); }, 10);
        }
      });
    })();

    /* ===== Price toggle (kept) ===== */
    $(document).ready(function () {
      var ckbox = $('#priceChecked');
      $('input').on('click',function () {
        if (ckbox.is(':checked')) {
          $(".price").text('50,000 RWF').removeClass("important2").addClass("important1");
        } else {
          $(".price").text('400,000 RWF').removeClass("important1").addClass("important2");
        }
      });
    });
  </script>
  <?php } ?>
</div>
</body>
</html>
