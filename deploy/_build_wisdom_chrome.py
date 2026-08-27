"""Render Wisdom landscape student-card chrome (no dynamic text/photo)."""
from PIL import Image, ImageDraw
import os

# 20 px per mm → CR80 85.6 × 54 mm (sharp at 300 dpi)
W, H = 1712, 1080
TEAL = (0, 130, 142)
NAVY = (4, 73, 107)
BG = (248, 248, 248)


def P(xp, yp):
    return (xp / 100.0 * W, yp / 100.0 * H)


im = Image.new("RGB", (W, H), BG)
d = ImageDraw.Draw(im)

# --- Header: navy back-ribbon (left fold + peek above teal) ---
d.polygon(
    [P(0, 5.8), P(56.2, 5.2), P(54.0, 8.2), P(0, 8.6)],
    fill=NAVY,
)
d.polygon(
    [P(0, 5.8), P(6.2, 5.8), P(0.4, 23.6), P(0, 23.6)],
    fill=NAVY,
)
# Teal folded tab to the top-left
d.polygon(
    [P(8.3, 0), P(22.9, 0), P(20.4, 8.2), P(5.3, 8.2)],
    fill=TEAL,
)
# Main teal header banner (slanted right, full enough for the school name)
d.polygon(
    [P(5.3, 8.0), P(99.4, 8.0), P(93.8, 23.6), P(0.0, 23.6)],
    fill=TEAL,
)
# Navy strip visible around the logo (right of logo circle)
d.polygon(
    [P(16.8, 8.0), P(21.9, 8.0), P(16.7, 23.6), P(14.8, 23.6)],
    fill=NAVY,
)

# --- STUDENT ID CARD navy ribbon (tucks under the photo circle) ---
d.polygon(
    [P(24.6, 36.2), P(76.8, 36.2), P(72.6, 47.4), P(30.0, 47.4)],
    fill=NAVY,
)

# --- Footer navy left cap ---
d.polygon(
    [P(0, 79.5), P(7.0, 79.5), P(1.4, 96.2), P(0, 96.2)],
    fill=NAVY,
)
# Teal ID bar
d.polygon(
    [P(5.2, 84.8), P(39.6, 84.8), P(35.8, 95.4), P(1.6, 95.4)],
    fill=TEAL,
)
# Three parallel teal stripes (increasing thickness, same slant)
d.polygon(
    [P(40.6, 84.8), P(41.7, 84.8), P(38.1, 95.4), P(37.0, 95.4)],
    fill=TEAL,
)
d.polygon(
    [P(43.0, 84.8), P(44.9, 84.8), P(41.2, 95.4), P(39.3, 95.4)],
    fill=TEAL,
)
d.polygon(
    [P(46.3, 84.8), P(48.9, 84.8), P(45.2, 95.4), P(42.6, 95.4)],
    fill=TEAL,
)

out_dir = r"C:\xampp7\htdocs\Xander-school\public\assets\images\cards"
os.makedirs(out_dir, exist_ok=True)
out = os.path.join(out_dir, "wisdom_landscape_chrome.png")
im.save(out, "PNG")
print("wrote", out, im.size)
