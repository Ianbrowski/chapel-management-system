import pymupdf as fitz
import os
import shutil

def hex_to_rgb(hex_str):
    hex_str = hex_str.lstrip('#')
    return tuple(int(hex_str[i:i+2], 16) / 255.0 for i in (0, 2, 4))

# Canvas dimensions (High-res landscape A2/A1 equivalent: 3200 x 2000 points)
WIDTH = 3200
HEIGHT = 2000

doc = fitz.open()
page = doc.new_page(width=WIDTH, height=HEIGHT)

# Colors
C_BG = hex_to_rgb("#F8FAFC")
C_CANVAS_BORDER = hex_to_rgb("#CBD5E1")
C_HEADER_BLUE = hex_to_rgb("#0284C7")
C_HEADER_DARK = hex_to_rgb("#0369A1")
C_HEADER_GREEN = hex_to_rgb("#047857")
C_TEXT_DARK = hex_to_rgb("#0F172A")
C_TEXT_MUTED = hex_to_rgb("#64748B")
C_ROW_ALT = hex_to_rgb("#F1F5F9")
C_BORDER = hex_to_rgb("#0284C7")
C_LINE_BLUE = hex_to_rgb("#0284C7")
C_LINE_GREEN = hex_to_rgb("#059669")
C_PK = hex_to_rgb("#EA580C")
C_FK = hex_to_rgb("#0284C7")
C_UK = hex_to_rgb("#059669")
C_WHITE = (1.0, 1.0, 1.0)

# Fill Background
page.draw_rect(fitz.Rect(0, 0, WIDTH, HEIGHT), color=None, fill=C_BG)

# Title Header Box
title_rect = fitz.Rect(60, 40, 1100, 140)
page.draw_rect(title_rect, color=hex_to_rgb("#CBD5E1"), fill=C_WHITE)
page.insert_text(fitz.Point(85, 75), "CENTRALIZED CHAPEL INFORMATION MANAGEMENT SYSTEM", fontsize=18, fontname="helv", fontfile=None, color=C_HEADER_DARK)
page.insert_text(fitz.Point(85, 98), "COMPLETE ENTITY-RELATIONSHIP DIAGRAM (ERD) — MODULES 1 TO 5", fontsize=13, fontname="helv", color=C_TEXT_DARK)
page.insert_text(fitz.Point(85, 120), "Modules 1: Liturgical Calendar | 2: Booking Requests | 3: Sacramental Registry | 4: Members | 5: Clergy", fontsize=10.5, fontname="helv", color=C_TEXT_MUTED)

# Legend Box
leg_rect = fitz.Rect(1140, 40, 1720, 140)
page.draw_rect(leg_rect, color=hex_to_rgb("#CBD5E1"), fill=C_WHITE)
page.insert_text(fitz.Point(1160, 70), "NOTATION & RELATIONSHIP LEGEND", fontsize=11, fontname="helv", color=C_TEXT_DARK)

# PK badge
page.draw_rect(fitz.Rect(1160, 85, 1195, 102), color=None, fill=C_PK)
page.insert_text(fitz.Point(1166, 98), "PK", fontsize=9, fontname="helv", color=C_WHITE)
page.insert_text(fitz.Point(1205, 96), "Primary Key", fontsize=10, fontname="helv", color=C_TEXT_DARK)

# FK badge
page.draw_rect(fitz.Rect(1310, 85, 1345, 102), color=None, fill=C_FK)
page.insert_text(fitz.Point(1316, 98), "FK", fontsize=9, fontname="helv", color=C_WHITE)
page.insert_text(fitz.Point(1355, 96), "Foreign Key", fontsize=10, fontname="helv", color=C_TEXT_DARK)

# UK badge
page.draw_rect(fitz.Rect(1460, 85, 1495, 102), color=None, fill=C_UK)
page.insert_text(fitz.Point(1466, 98), "UK", fontsize=9, fontname="helv", color=C_WHITE)
page.insert_text(fitz.Point(1505, 96), "Unique Key", fontsize=10, fontname="helv", color=C_TEXT_DARK)

page.insert_text(fitz.Point(1160, 124), "──||──<o  One-to-Many Crow's Foot Relational Constraint (1 : N)", fontsize=10, fontname="helv", color=C_HEADER_BLUE)

# Entity Data Definitions
entities = {
    "chapels": {
        "title": "chapels",
        "sub": "Core Spine • 7 Attributes",
        "x": 60, "y": 180, "w": 360, "header_color": C_HEADER_DARK,
        "rows": [
            ("PK", "chapel_id*", "int(11)"),
            ("", "chapel_name*", "varchar(100)"),
            ("", "location*", "varchar(255)"),
            ("", "patron_saint", "varchar(255)"),
            ("", "seating_capacity", "int(11)"),
            ("", "status*", "enum('Active'..)"),
            ("", "sunday_service_time", "varchar(255)")
        ]
    },
    "users": {
        "title": "users",
        "sub": "M4 & M5 Core Actors • Member | Priest | Staff | Admin",
        "x": 60, "y": 500, "w": 380, "header_color": C_HEADER_DARK,
        "rows": [
            ("PK", "id*", "int(11)"),
            ("UK", "user*", "varchar(50)"),
            ("", "role*", "varchar(20)"),
            ("", "status*", "varchar(20)"),
            ("FK", "chapel_id", "int(11) [Branch Scope]"),
            ("", "full_name*", "varchar(100)"),
            ("", "email*", "varchar(150)"),
            ("", "phone", "varchar(30)"),
            ("", "duty_start_time", "time"),
            ("", "duty_end_time", "time")
        ]
    },
    "pastor_event_types": {
        "title": "pastor_event_types",
        "sub": "M5 Entity • Clergy Availability (3 Attributes)",
        "x": 60, "y": 940, "w": 360, "header_color": C_HEADER_BLUE,
        "rows": [
            ("PK", "id*", "int(11)"),
            ("FK", "pastor_id*", "int(11) [Clergy Priest]"),
            ("", "event_type*", "varchar(50) [Baptism, Wedding]")
        ]
    },
    "mass_schedules": {
        "title": "mass_schedules",
        "sub": "M1 Entity • Liturgical Calendar (6 Attributes)",
        "x": 520, "y": 180, "w": 360, "header_color": C_HEADER_BLUE,
        "rows": [
            ("PK", "id*", "int(11)"),
            ("FK", "chapel_id*", "int(11) [Chapel Venue]"),
            ("", "day_of_week*", "varchar(20) [Sunday..]"),
            ("", "time_slot*", "time [06:00:00]"),
            ("", "language", "varchar(50) [Tagalog/English]")
        ]
    },
    "schedules": {
        "title": "schedules",
        "sub": "M1 Entity • Liturgical Time Slots & Conflicts (7 Attributes)",
        "x": 520, "y": 480, "w": 380, "header_color": C_HEADER_BLUE,
        "rows": [
            ("PK", "id*", "int(11)"),
            ("FK", "chapel_id*", "int(11) [Chapel Venue]"),
            ("FK", "pastor_id", "int(11) [Assigned Priest]"),
            ("", "schedule_date*", "date"),
            ("", "start_time*", "time"),
            ("", "end_time*", "time"),
            ("", "status*", "varchar(20) [Available|Booked]")
        ]
    },
    "events": {
        "title": "events",
        "sub": "M2 Central Transactional Entity • Maker-Checker Queue",
        "x": 980, "y": 360, "w": 400, "header_color": C_HEADER_DARK,
        "rows": [
            ("PK", "event_id*", "int(11)"),
            ("FK", "requested_by*", "int(11) [Member Parishioner]"),
            ("FK", "service_id*", "int(11) [Service Type]"),
            ("FK", "variant_id", "int(11) [Variant Scope]"),
            ("FK", "chapel_id*", "int(11) [Venue Chapel]"),
            ("FK", "pastor_id", "int(11) [Celebrant Priest]"),
            ("", "scheduled_start*", "datetime"),
            ("", "scheduled_end*", "datetime"),
            ("", "status*", "varchar(20) [Pending|Approved]"),
            ("", "total_fee*", "decimal(10,2)"),
            ("", "payment_status*", "varchar(20) [Paid|Unpaid]")
        ]
    },
    "service_request_addons": {
        "title": "service_request_addons",
        "sub": "M2 Entity • Add-on Line Items (4 Attributes)",
        "x": 980, "y": 880, "w": 360, "header_color": C_HEADER_BLUE,
        "rows": [
            ("PK", "id*", "int(11)"),
            ("FK", "event_id*", "int(11) [References events]"),
            ("FK", "addon_id*", "int(11) [References charge]"),
            ("", "amount*", "decimal(10,2)")
        ]
    },
    "service_categories": {
        "title": "service_categories",
        "sub": "M2 Entity • 5 Core Attributes (of 7)",
        "x": 1470, "y": 180, "w": 360, "header_color": C_HEADER_BLUE,
        "rows": [
            ("PK", "id*", "int(11)"),
            ("UK", "code*", "varchar(50)"),
            ("", "name*", "varchar(100)"),
            ("", "display_order*", "int(11)"),
            ("", "status*", "enum('active'..)")
        ]
    },
    "services": {
        "title": "services",
        "sub": "M2 Entity • 7 Core Attributes (of 11)",
        "x": 1470, "y": 480, "w": 370, "header_color": C_HEADER_BLUE,
        "rows": [
            ("PK", "id*", "int(11)"),
            ("FK", "category_id*", "int(11)"),
            ("UK", "code*", "varchar(50)"),
            ("", "name*", "varchar(150)"),
            ("", "reference_source*", "varchar(255)"),
            ("", "effective_date*", "date"),
            ("", "status*", "enum('active'..)")
        ]
    },
    "service_schedules": {
        "title": "service_schedules",
        "sub": "M2 Entity • 7 Core Attributes (of 9)",
        "x": 1470, "y": 860, "w": 360, "header_color": C_HEADER_BLUE,
        "rows": [
            ("PK", "id*", "int(11)"),
            ("FK", "service_id*", "int(11)"),
            ("", "schedule_type*", "varchar(50)"),
            ("", "day_of_week*", "varchar(50)"),
            ("", "start_time", "time"),
            ("", "end_time", "time"),
            ("", "status*", "enum('active'..)")
        ]
    },
    "service_variants": {
        "title": "service_variants",
        "sub": "M2 Entity • 8 Core Attributes (of 13)",
        "x": 1940, "y": 180, "w": 360, "header_color": C_HEADER_BLUE,
        "rows": [
            ("PK", "id*", "int(11)"),
            ("FK", "service_id*", "int(11)"),
            ("", "name*", "varchar(100)"),
            ("", "price*", "decimal(10,2)"),
            ("", "schedule_type*", "varchar(50)"),
            ("", "included_sponsors*", "int(11)"),
            ("", "is_current*", "tinyint(1)"),
            ("", "status*", "enum('active'..)")
        ]
    },
    "service_requirements": {
        "title": "service_requirements",
        "sub": "M2 Entity • 5 Core Attributes (of 7)",
        "x": 1940, "y": 520, "w": 360, "header_color": C_HEADER_BLUE,
        "rows": [
            ("PK", "id*", "int(11)"),
            ("FK", "service_id*", "int(11)"),
            ("", "requirement*", "varchar(255)"),
            ("", "is_required*", "tinyint(1)"),
            ("", "status*", "enum('active'..)")
        ]
    },
    "service_additional_charges": {
        "title": "service_additional_charges",
        "sub": "M2 Entity • 6 Core Attributes (of 8)",
        "x": 1940, "y": 820, "w": 360, "header_color": C_HEADER_BLUE,
        "rows": [
            ("PK", "id*", "int(11)"),
            ("FK", "service_id*", "int(11)"),
            ("", "name*", "varchar(100)"),
            ("", "price*", "decimal(10,2)"),
            ("", "is_optional*", "tinyint(1)"),
            ("", "status*", "enum('active'..)")
        ]
    },
    "sacramental_records": {
        "title": "sacramental_records",
        "sub": "M3 Canonical Registry • Baptism | Matrimony | Confirmation",
        "x": 2400, "y": 240, "w": 400, "header_color": C_HEADER_GREEN,
        "rows": [
            ("PK", "record_id*", "int(11)"),
            ("FK", "chapel_id", "int(11) [Branch Scope]"),
            ("", "record_type*", "enum('Baptism'..)"),
            ("", "person_name*", "varchar(150)"),
            ("", "event_date*", "date"),
            ("", "book_number", "varchar(20) [Book #]"),
            ("", "page_number", "varchar(20) [Page #]"),
            ("", "reference_id", "varchar(30) [Registry Ref]"),
            ("FK", "encoded_by", "int(11) [Parish Staff]")
        ]
    },
    "certificate_types": {
        "title": "certificate_types",
        "sub": "M3 Entity • 5 Core Attributes (of 7)",
        "x": 2400, "y": 640, "w": 360, "header_color": C_HEADER_BLUE,
        "rows": [
            ("PK", "id*", "int(11)"),
            ("FK", "service_id", "int(11)"),
            ("UK", "name*", "varchar(100)"),
            ("", "price_per_copy*", "decimal(10,2)"),
            ("", "status*", "enum('active'..)")
        ]
    },
    "certificate_issuances": {
        "title": "certificate_issuances",
        "sub": "M3 Certified Copies Issued • Direct Registry Link",
        "x": 2400, "y": 920, "w": 380, "header_color": C_HEADER_GREEN,
        "rows": [
            ("PK", "issuance_id*", "int(11)"),
            ("FK", "record_id*", "int(11) [References sacramental_records]"),
            ("", "certificate_no*", "varchar(30) [Official Ref]"),
            ("", "issued_to*", "varchar(150)"),
            ("FK", "issued_by", "int(11) [Parish Officer]"),
            ("", "issued_at*", "timestamp")
        ]
    }
}

ROW_HEIGHT = 28
HEADER_HEIGHT = 46

# Store row center coordinates for drawing connections
row_coords = {}

# Draw all entities
for e_key, e in entities.items():
    x, y, w = e["x"], e["y"], e["w"]
    n_rows = len(e["rows"])
    total_h = HEADER_HEIGHT + n_rows * ROW_HEIGHT

    # Outer Card & Shadow
    card_rect = fitz.Rect(x, y, x + w, y + total_h)
    page.draw_rect(fitz.Rect(x + 3, y + 3, x + w + 3, y + total_h + 3), color=None, fill=hex_to_rgb("#E2E8F0"))
    page.draw_rect(card_rect, color=e["header_color"], fill=C_WHITE, width=1.5)

    # Header Box
    header_rect = fitz.Rect(x, y, x + w, y + HEADER_HEIGHT)
    page.draw_rect(header_rect, color=None, fill=e["header_color"])

    page.insert_text(fitz.Point(x + 14, y + 22), e["title"], fontsize=13, fontname="helv", color=C_WHITE)
    page.insert_text(fitz.Point(x + 14, y + 38), e["sub"], fontsize=9.5, fontname="helv", color=hex_to_rgb("#E0F2FE"))

    # Rows
    for idx, (badge, name, dtype) in enumerate(e["rows"]):
        row_y = y + HEADER_HEIGHT + idx * ROW_HEIGHT
        row_rect = fitz.Rect(x + 1, row_y, x + w - 1, row_y + ROW_HEIGHT)

        # Alternating background
        if idx % 2 == 1:
            page.draw_rect(row_rect, color=None, fill=C_ROW_ALT)

        # Divider line
        if idx > 0:
            page.draw_line(fitz.Point(x + 1, row_y), fitz.Point(x + w - 1, row_y), color=hex_to_rgb("#E2E8F0"), width=0.8)

        text_base_y = row_y + 18

        # Badge
        left_offset = x + 12
        if badge:
            badge_color = C_PK if badge == "PK" else (C_FK if badge == "FK" else C_UK)
            b_rect = fitz.Rect(left_offset, row_y + 5, left_offset + 22, row_y + 22)
            page.draw_rect(b_rect, color=None, fill=badge_color)
            page.insert_text(fitz.Point(left_offset + 4, text_base_y - 3), badge, fontsize=8.5, fontname="helv", color=C_WHITE)
            left_offset += 28

        # Name
        page.insert_text(fitz.Point(left_offset, text_base_y), name, fontsize=10.5, fontname="helv", color=C_TEXT_DARK)

        # Type (Right aligned)
        type_w = len(dtype) * 5.6
        page.insert_text(fitz.Point(x + w - 12 - type_w, text_base_y), dtype, fontsize=9.5, fontname="helv", color=C_TEXT_MUTED)

        # Record coordinate for connection
        clean_name = name.rstrip('*').split()[0]
        row_coords[f"{e_key}.{clean_name}"] = {
            "left": fitz.Point(x, row_y + ROW_HEIGHT / 2),
            "right": fitz.Point(x + w, row_y + ROW_HEIGHT / 2)
        }

# Helper to draw Crow's Foot Connector
def draw_connector(from_key, to_key, from_side="right", to_side="left", color=C_LINE_BLUE):
    if from_key not in row_coords or to_key not in row_coords:
        return
    p1 = row_coords[from_key][from_side]
    p2 = row_coords[to_key][to_side]

    mid_x = (p1.x + p2.x) / 2
    c1 = fitz.Point(mid_x, p1.y)
    c2 = fitz.Point(mid_x, p2.y)

    page.draw_bezier(p1, c1, c2, p2, color=color, width=2)

    # Draw Crow's foot on target (to_side)
    dir_x = -1 if to_side == "left" else 1
    # 3 prongs
    page.draw_line(fitz.Point(p2.x + dir_x * 12, p2.y - 7), p2, color=color, width=1.8)
    page.draw_line(fitz.Point(p2.x + dir_x * 12, p2.y + 7), p2, color=color, width=1.8)
    page.draw_line(fitz.Point(p2.x + dir_x * 14, p2.y - 8), fitz.Point(p2.x + dir_x * 14, p2.y + 8), color=color, width=1.8)

    # Draw double hash on source (from_side)
    s_dir = 1 if from_side == "right" else -1
    page.draw_line(fitz.Point(p1.x + s_dir * 6, p1.y - 6), fitz.Point(p1.x + s_dir * 6, p1.y + 6), color=color, width=1.8)
    page.draw_line(fitz.Point(p1.x + s_dir * 10, p1.y - 6), fitz.Point(p1.x + s_dir * 10, p1.y + 6), color=color, width=1.8)

# Connect all relationships
draw_connector("chapels.chapel_id", "users.chapel_id", "left", "left", C_HEADER_DARK)
draw_connector("chapels.chapel_id", "mass_schedules.chapel_id", "right", "left", C_HEADER_DARK)
draw_connector("chapels.chapel_id", "schedules.chapel_id", "right", "left", C_HEADER_DARK)
draw_connector("chapels.chapel_id", "events.chapel_id", "right", "left", C_HEADER_DARK)
draw_connector("chapels.chapel_id", "sacramental_records.chapel_id", "right", "left", C_HEADER_GREEN)

draw_connector("users.id", "pastor_event_types.pastor_id", "left", "left", C_LINE_BLUE)
draw_connector("users.id", "schedules.pastor_id", "right", "left", C_LINE_BLUE)
draw_connector("users.id", "events.requested_by", "right", "left", C_LINE_BLUE)
draw_connector("users.id", "events.pastor_id", "right", "left", C_LINE_BLUE)

draw_connector("service_categories.id", "services.category_id", "right", "left", C_LINE_BLUE)
draw_connector("services.id", "service_variants.service_id", "right", "left", C_LINE_BLUE)
draw_connector("services.id", "service_requirements.service_id", "right", "left", C_LINE_BLUE)
draw_connector("services.id", "service_additional_charges.service_id", "right", "left", C_LINE_BLUE)
draw_connector("services.id", "service_schedules.service_id", "left", "left", C_LINE_BLUE)

draw_connector("services.id", "events.service_id", "left", "right", C_LINE_BLUE)
draw_connector("service_variants.id", "events.variant_id", "left", "right", C_LINE_BLUE)

draw_connector("events.event_id", "service_request_addons.event_id", "left", "left", C_LINE_BLUE)
draw_connector("service_additional_charges.id", "service_request_addons.addon_id", "left", "right", C_LINE_BLUE)

draw_connector("services.id", "certificate_types.service_id", "right", "left", C_LINE_BLUE)
draw_connector("sacramental_records.record_id", "certificate_issuances.record_id", "right", "right", C_HEADER_GREEN)
draw_connector("users.id", "sacramental_records.encoded_by", "right", "left", C_HEADER_GREEN)

# Output Paths
pdf_path = "c:/xampp/htdocs/ORIGINAL/docs/ERD_MODULES_1_TO_5.pdf"
png_path = "c:/xampp/htdocs/ORIGINAL/docs/ERD_MODULES_1_TO_5.png"

# Save Vector PDF
doc.save(pdf_path)
print(f"Saved Vector PDF: {pdf_path}")

# Render to High-Res PNG (300 DPI equivalent: zoom = 2.0 -> 6400 x 4000)
mat = fitz.Matrix(2.0, 2.0)
pix = page.get_pixmap(matrix=mat, alpha=False)
pix.save(png_path)
print(f"Saved High-Res PNG: {png_path} ({pix.width}x{pix.height})")

# Copy to conversation artifact directory for preview
art_dir = "C:/Users/Admin/.gemini/antigravity/brain/a6cced25-c91e-4b25-b49f-55f6bc320ce3"
if os.path.isdir(art_dir):
    shutil.copy2(png_path, os.path.join(art_dir, "ERD_MODULES_1_TO_5.png"))
    shutil.copy2(pdf_path, os.path.join(art_dir, "ERD_MODULES_1_TO_5.pdf"))
    print("Copied to brain artifact directory.")
