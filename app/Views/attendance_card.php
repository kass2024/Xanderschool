<!DOCTYPE html>
<html>
<head>

<title>Student Attendance Scanner</title>

<meta name="viewport" content="width=device-width, initial-scale=1">

<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

<style>

body{
background:#f1f3f7;
font-family:Segoe UI,Arial;
height:100vh;
display:flex;
align-items:center;
justify-content:center;
margin:0;
}

.kiosk{
width:950px;
background:white;
border-radius:18px;
box-shadow:0 20px 40px rgba(0,0,0,.12);
padding:40px;
transition:.3s;
}

.title{
text-align:center;
font-size:34px;
font-weight:700;
margin-bottom:30px;
color:#2c3e50;
}

.layout{
display:flex;
gap:40px;
align-items:center;
}

.photo{
width:240px;
height:240px;
object-fit:cover;
border-radius:14px;
border:6px solid #f1f3f7;
background:#fafafa;
}

.info{
flex:1;
}

.name{
font-size:36px;
font-weight:700;
color:#2c3e50;
}

.regno{
font-size:20px;
color:#6c757d;
margin-top:6px;
}

.status{
font-size:48px;
font-weight:800;
padding:10px 28px;
border-radius:12px;
margin-top:20px;
display:inline-block;
}

.status.in{
background:#e8f8ef;
color:#1f9e55;
}

.status.out{
background:#fdeaea;
color:#d12c2c;
}

.waiting{
font-size:18px;
color:#8a8a8a;
margin-top:12px;
}

.flash-green{background:#e8f8ef!important;}
.flash-red{background:#fdeaea!important;}

.indicator{
height:6px;
background:#e1e1e1;
margin-top:25px;
border-radius:4px;
transition:.2s;
}

.indicator.active{
background:#1f9e55;
}

</style>

</head>

<body>

<div class="kiosk" id="container">

<div class="title">
Student Attendance Scanner
</div>

<div class="layout">

<img id="photo"
class="photo"
src="<?= base_url('assets/images/no-photo.png') ?>"
onerror="this.src='<?= base_url('assets/images/no-photo.png') ?>'">

<div class="info">

<div id="name" class="name">
Waiting for card...
</div>

<div id="regno" class="regno"></div>

<div id="status"></div>

<div class="waiting">
Tap student card on reader
</div>

<div id="indicator" class="indicator"></div>

</div>

</div>

</div>
<script>

document.addEventListener("DOMContentLoaded", function () {

let buffer = "";
let scanning = false;
let lastUID = null;

const indicator = document.getElementById("indicator");
const container = document.getElementById("container");

const fallback = "<?= base_url('assets/images/no-photo.png') ?>";
const apiURL = "<?= base_url('scan-card') ?>";


// ================= NORMALIZER =================
function normalizeUID(uid){

uid = uid.trim();
if(!uid) return "";

// Decimal → HEX
if(/^\d+$/.test(uid)){
const num = BigInt(uid);
uid = num.toString(16).toUpperCase();
}

// Clean HEX
uid = uid.replace(/[^A-Fa-f0-9]/g,'').toUpperCase();

return uid;
}


// ================= RFID INPUT =================
document.addEventListener("keypress", function(e){

indicator.classList.add("active");
setTimeout(()=>indicator.classList.remove("active"),120);

if(e.key === "Enter"){

let raw = buffer.trim();
buffer = "";

if(raw.length > 3){
let uid = normalizeUID(raw);
console.log("Scanned UID:", uid);
scan(uid);
}

}else{
buffer += e.key;
}

});


// ================= URL TEST MODE =================
setTimeout(()=>{

const params = new URLSearchParams(window.location.search);
const testCard = params.get("card");

if(testCard){
let uid = normalizeUID(testCard);
console.log("URL Test UID:", uid);
scan(uid);
}

},300);


// ================= API SCAN =================
function scan(uid){

if(scanning) return;

if(uid === lastUID){
console.log("Duplicate ignored:", uid);
return;
}

scanning = true;
lastUID = uid;

fetch(apiURL + "?card=" + encodeURIComponent(uid), {
method: "GET",
cache: "no-store"
})
.then(res => res.json())
.then(data => {

console.log("API:", data);

if(data.success){
showStudent(data);
}else{
showError(data.message || "Card not registered");
}

})
.catch(err => {
console.error("Scan error:", err);
showError("System error");
})
.finally(() => {
setTimeout(()=> scanning = false, 1200);
});

}


// ================= SHOW =================
function showStudent(data){

document.getElementById("name").innerText = data.student?.name || "Unknown";
document.getElementById("regno").innerText = data.student?.regno || "";

let photo = document.getElementById("photo");
photo.src = (data.student?.photo || fallback) + "?v=" + Date.now();

let status = document.getElementById("status");
status.innerText = data.status;

if(data.status === "IN"){
status.className = "status in";
flashGreen();
}else{
status.className = "status out";
flashRed();
}

}


// ================= ERROR =================
function showError(msg){

document.getElementById("name").innerText = msg;
document.getElementById("regno").innerText = "";
document.getElementById("status").innerHTML = "";
document.getElementById("photo").src = fallback;

flashRed();

}


// ================= EFFECTS =================
function flashGreen(){
container.classList.add("flash-green");
setTimeout(()=>container.classList.remove("flash-green"),1000);
}

function flashRed(){
container.classList.add("flash-red");
setTimeout(()=>container.classList.remove("flash-red"),1000);
}

});

</script>
</body>
</html>