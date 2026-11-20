<?php
/**
 * Services Management View — Sacramental & Liturgical Services Catalog
 * Admin & Cathedral Admin control center.
 */
$catalog = $services_catalog ?? [];
$certTypes = $certificate_types ?? [];
?>
<link rel="stylesheet" href="assets/css/workspace.css?v=3.0">

<div class="workspace" style="min-height: 100%; flex: 1; display: flex; flex-direction: column; gap: 14px; padding: 14px; box-sizing: border-box;">

    <!-- ─── PAGE HEADER ─── -->
    <div class="page-header-bar" style="display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; border-bottom: 1px solid var(--border); padding-bottom: 8px; margin-bottom: 6px;">
        <div style="display: flex; align-items: center; gap: 12px;">
            <div class="page-header-icon" style="width: 38px; height: 38px; border-radius: 9px; background: rgba(201,168,76,0.14); color: var(--accent); display: flex; align-items: center; justify-content: center; font-size: 17px; flex-shrink: 0;">
                <i class="fa-solid fa-hand-holding-heart"></i>
            </div>
            <div>
                <h1 class="page-title" style="font-family: var(--font-family, 'Inter', sans-serif); font-size: var(--font-page-title, 20px); font-weight: 700; color: var(--text); margin: 0; line-height: 1.2;">
                    Service Catalog &amp; Fee Schedule
                </h1>
                <p class="page-subtitle" style="font-size: var(--font-page-subtitle, 14px); color: var(--text2); margin: 3px 0 0 0;">Configure parish liturgical rites, sacrament rate tiers, certificate fees, and requirements</p>
            </div>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
            <a href="dashboard.php?section=event_requests" class="btn btn-ghost btn-sm" style="font-size: 11.5px; background: var(--surface); border: 1px solid var(--line); color: var(--ink); border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-calendar-check text-gold"></i> Event Requests
            </a>
            <a href="dashboard.php?section=request_certificate" class="btn btn-ghost btn-sm" style="font-size: 11.5px; background: var(--surface); border: 1px solid var(--line); color: var(--ink); border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                <i class="fa-solid fa-certificate text-gold"></i> Certificate Requests
            </a>
            <button class="btn btn-ghost btn-sm" onclick="window.location.reload();" title="Refresh Catalog" style="font-size: 11.5px; background: var(--surface); border: 1px solid var(--line); color: var(--ink); border-radius: 8px;">
                <i class="fa-solid fa-arrows-rotate"></i> Refresh
            </button>
        </div>
    </div>

    <!-- ─── TOP SERVICE CATEGORY ICON TABS ─── -->
    <?php
        $totalServiceCount = count($certTypes);
        foreach ($catalog as $c) {
            if (($c['code'] ?? '') === 'certificates') continue;
            $totalServiceCount += count($c['services'] ?? []);
        }
        $catIcons = [
            'sacraments'   => 'fa-dove',
            'liturgical'   => 'fa-church',
            'blessings'    => 'fa-hands-praying',
            'certificates' => 'fa-file-shield',
        ];
    ?>
    <div id="admin-service-top-tabs" style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
        <button type="button" class="srv-top-tab active" data-cat="all" onclick="filterAdminServiceCategory('all', this)"
                style="display: inline-flex; align-items: center; gap: 7px; padding: 7px 14px; border-radius: 999px; border: 1px solid #111111; background: #111111; color: #FFFFFF; font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.15s ease;">
            <i class="fa-solid fa-layer-group"></i>
            <span>All Services</span>
            <span style="font-size: 10.5px; opacity: 0.85; background: rgba(255,255,255,0.18); padding: 1px 7px; border-radius: 999px;"><?= (int)$totalServiceCount ?></span>
        </button>

        <?php foreach ($catalog as $cat):
            $code = (string)($cat['code'] ?? '');
            if ($code === 'certificates') continue;
            $icon = $catIcons[strtolower($code)] ?? 'fa-church';
            $cnt = count($cat['services'] ?? []);
        ?>
        <button type="button" class="srv-top-tab" data-cat="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>" onclick="filterAdminServiceCategory('<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>', this)"
                style="display: inline-flex; align-items: center; gap: 7px; padding: 7px 14px; border-radius: 999px; border: 1px solid var(--border); background: var(--surface); color: var(--text); font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.15s ease;">
            <i class="fa-solid <?= $icon ?>" style="color: var(--accent);"></i>
            <span><?= htmlspecialchars($cat['name']) ?></span>
            <span style="font-size: 10.5px; color: var(--text2); background: var(--bg3, #F4F4F5); padding: 1px 7px; border-radius: 999px;"><?= (int)$cnt ?></span>
        </button>
        <?php endforeach; ?>

        <button type="button" class="srv-top-tab" data-cat="certificates" onclick="filterAdminServiceCategory('certificates', this)"
                style="display: inline-flex; align-items: center; gap: 7px; padding: 7px 14px; border-radius: 999px; border: 1px solid var(--border); background: var(--surface); color: var(--text); font-size: 12px; font-weight: 600; cursor: pointer; transition: all 0.15s ease;">
            <i class="fa-solid fa-stamp" style="color: var(--accent);"></i>
            <span>Certificates</span>
            <span style="font-size: 10.5px; color: var(--text2); background: var(--bg3, #F4F4F5); padding: 1px 7px; border-radius: 999px;"><?= count($certTypes) ?></span>
        </button>
    </div>

    <!-- ─── CERTIFICATES MANAGEMENT STRIP ─── -->
    <div class="ent-card srv-category-section" data-cat="certificates" style="border: 1px solid var(--border); border-radius: 12px; background: var(--panel-bg); overflow: hidden; flex-shrink: 0; box-shadow: none;">
        <div class="ent-card-header" style="padding: 12px 16px; background: var(--panel-header-bg); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center;">
            <div style="font-family: var(--font-family, 'Inter', sans-serif); font-size: 0.95rem; font-weight: 600; color: var(--text); display: flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-stamp" style="color: var(--accent);"></i> Ecclesiastical Certificate Rates
            </div>
            <span style="font-size: 0.72rem; color: var(--text2);">Official Certified Copies</span>
        </div>
        <div class="ent-card-body" style="padding: 14px 16px; display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 12px; background: var(--panel-bg);">
            <?php foreach ($certTypes as $ct): ?>
                <div style="background: var(--hover-bg); border: 1px solid var(--border); border-radius: 8px; padding: 12px 14px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div style="font-weight: 600; font-size: 0.85rem; color: var(--text);"><?= htmlspecialchars($ct['name']) ?> Certificate</div>
                        <div style="font-size: 0.8rem; color: var(--text); font-weight: 600; margin-top: 2px;">
                            ₱<?= number_format((float)$ct['price_per_copy'], 2) ?> <span style="font-size: 0.7rem; color: var(--text2); font-weight: normal;">/ copy</span>
                        </div>
                    </div>
                    <button class="btn btn-ghost btn-sm" 
                            data-id="<?= (int)$ct['id'] ?>"
                            data-name="<?= htmlspecialchars($ct['name'], ENT_QUOTES, 'UTF-8') ?>"
                            data-price="<?= (float)$ct['price_per_copy'] ?>"
                            onclick="openCertRateModal(this)" 
                            style="font-size: 0.75rem; padding: 4px 10px; border-radius: 6px;">
                        <i class="fa-solid fa-pen-to-square"></i> Edit Rate
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ─── CATEGORY & SERVICE ACCORDIONS ─── -->
    <?php foreach ($catalog as $cat): ?>
        <?php if ($cat['code'] === 'certificates') continue; ?>
        <div class="srv-category-section" data-cat="<?= htmlspecialchars((string)$cat['code'], ENT_QUOTES, 'UTF-8') ?>" style="margin-top: 8px;">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                <div style="font-family: var(--font-family, 'Inter', sans-serif); font-size: 1rem; font-weight: 600; color: var(--text);">
                    <?= htmlspecialchars($cat['name']) ?>
                </div>
                <div style="flex: 1; height: 1px; background: var(--border);"></div>
                <span style="font-size: 0.72rem; color: var(--text2);"><?= count($cat['services']) ?> Services</span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr; gap: 12px;">
                <?php foreach ($cat['services'] as $srv): ?>
                    <div class="ent-card" style="border: 1px solid var(--border); border-radius: 12px; background: var(--panel-bg); overflow: hidden; box-shadow: none;">
                        <!-- Service Header -->
                        <div style="padding: 12px 16px; background: var(--panel-header-bg); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
                            <div>
                                <div style="font-family: var(--font-family, 'Inter', sans-serif); font-size: 0.95rem; font-weight: 600; color: var(--text); display: flex; align-items: center; gap: 8px;">
                                    <?= htmlspecialchars($srv['name']) ?>
                                    <span class="badge" style="background: var(--surface); color: var(--status-confirmed-text, #4ade80); border: 1px solid var(--status-confirmed-text, #4ade80); font-size: 11px; padding: 2px 7px; border-radius: 4px;">
                                        <?= strtoupper($srv['status']) ?>
                                    </span>
                                </div>
                                <?php if (!empty($srv['description'])): ?>
                                    <div style="font-size: 12px; color: var(--text2); margin-top: 2px;">
                                        <?= htmlspecialchars($srv['description']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div style="display: flex; gap: 6px;">
                                <button class="btn btn-outline-secondary btn-sm" 
                                        data-id="<?= (int)$srv['id'] ?>"
                                        data-name="<?= htmlspecialchars($srv['name'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-desc="<?= htmlspecialchars($srv['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-ref="<?= htmlspecialchars($srv['reference_source'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-eff="<?= htmlspecialchars($srv['effective_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                        data-is-ref="<?= (int)($srv['is_reference_only'] ?? 0) ?>"
                                        onclick="openServiceMetaModal(this)" 
                                        style="font-size: 11.5px; padding: 3px 8px;">
                                    <i class="fa-solid fa-gear"></i> Settings
                                </button>
                                <button class="btn btn-primary btn-sm" 
                                        data-id="<?= (int)$srv['id'] ?>"
                                        data-name="<?= htmlspecialchars($srv['name'], ENT_QUOTES, 'UTF-8') ?>"
                                        onclick="openAddVariantModal(this)" 
                                        style="font-size: 11.5px; padding: 3px 8px;">
                                    <i class="fa-solid fa-plus"></i> Add Variant
                                </button>
                            </div>
                        </div>

                        <!-- Service Body Grid (3-column: Variants, Requirements, Additional Charges) -->
                        <div style="padding: 12px 14px; display: grid; grid-template-columns: 1.4fr 1.2fr 1fr; gap: 14px;">
                            
                            <!-- 1. Variants & Rates -->
                            <div style="background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; padding: 10px;">
                                <div style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--accent); letter-spacing: 0.06em; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                                    <span><i class="fa-solid fa-tags"></i> Pricing Variants</span>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 6px;">
                                    <?php if (empty($srv['variants'])): ?>
                                        <div style="font-size: 12px; color: var(--text2); font-style: italic;">No variants configured.</div>
                                    <?php else: ?>
                                        <?php foreach ($srv['variants'] as $v): ?>
                                            <div style="background: var(--panel-bg); border: 1px solid var(--border); border-radius: 6px; padding: 6px 10px; display: flex; justify-content: space-between; align-items: center;">
                                                <div>
                                                    <div style="font-weight: 700; font-size: 12.5px; color: var(--text);">
                                                        <?= htmlspecialchars($v['name']) ?>
                                                        <span style="color: var(--accent); font-weight: 800; margin-left: 4px;">PHP <?= number_format((float)$v['price'], 2) ?></span>
                                                    </div>
                                                    <div style="font-size: 11.5px; color: var(--text2); line-height: 1.2;">
                                                        <?= htmlspecialchars($v['schedule_type']) ?>
                                                        <?php if (!empty($v['included_sponsors'])): ?>
                                                            • Includes <?= (int)$v['included_sponsors'] ?> sponsors
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <button class="btn-icon-sq" 
                                                        style="width: 24px; height: 24px;" 
                                                        data-id="<?= (int)$v['id'] ?>"
                                                        data-name="<?= htmlspecialchars($v['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                        data-price="<?= (float)$v['price'] ?>"
                                                        data-eff-date="<?= htmlspecialchars($v['effective_from'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                                        onclick="openEditPriceModal(this)" 
                                                        title="Update Rate / Create New Version">
                                                    <i class="fa-solid fa-pen" style="font-size: 11px;"></i>
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- 2. Requirements -->
                            <div style="background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; padding: 10px;">
                                <div style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--accent); letter-spacing: 0.06em; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                                    <span><i class="fa-solid fa-list-check"></i> Required Documents</span>
                                    <button class="btn btn-outline-primary btn-sm" 
                                            data-id="<?= (int)$srv['id'] ?>"
                                            data-name="<?= htmlspecialchars($srv['name'], ENT_QUOTES, 'UTF-8') ?>"
                                            onclick="openAddReqModal(this)" 
                                            style="font-size: 11.5px; padding: 2px 7px;">
                                        <i class="fa-solid fa-plus"></i> Add
                                    </button>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <?php if (empty($srv['requirements'])): ?>
                                        <div style="font-size: 12px; color: var(--text2); font-style: italic;">No specific requirements listed.</div>
                                    <?php else: ?>
                                        <?php foreach ($srv['requirements'] as $req): ?>
                                            <div style="background: var(--panel-bg); border: 1px solid var(--border); border-radius: 6px; padding: 5px 8px; display: flex; justify-content: space-between; align-items: center;">
                                                <div style="font-size: 12px; color: var(--text); line-height: 1.2;">
                                                    <i class="fa-solid fa-check" style="color: #10b981; font-size: 11px; margin-right: 4px;"></i>
                                                    <?= htmlspecialchars($req['requirement']) ?>
                                                </div>
                                                <form method="POST" action="dashboard.php" style="margin:0;" onsubmit="return confirm('Remove this requirement?');">
                                                    <input type="hidden" name="form_section" value="services">
                                                    <input type="hidden" name="service_action" value="remove_requirement">
                                                    <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                                    <input type="hidden" name="requirement_id" value="<?= (int)$req['id'] ?>">
                                                    <button type="submit" class="btn-icon-sq" style="width: 22px; height: 22px; color: #ef4444;" title="Remove Requirement">
                                                        <i class="fa-solid fa-xmark" style="font-size: 11px;"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- 3. Additional Charges / Optional Add-ons -->
                            <div style="background: var(--bg2); border: 1px solid var(--border); border-radius: 8px; padding: 10px;">
                                <div style="font-size: 11.5px; font-weight: 700; text-transform: uppercase; color: var(--accent); letter-spacing: 0.06em; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                                    <span><i class="fa-solid fa-circle-plus"></i> Optional Add-ons</span>
                                    <button class="btn btn-outline-primary btn-sm" 
                                            data-id="<?= (int)$srv['id'] ?>"
                                            data-name="<?= htmlspecialchars($srv['name'], ENT_QUOTES, 'UTF-8') ?>"
                                            onclick="openAddChargeModal(this)" 
                                            style="font-size: 11.5px; padding: 2px 7px;">
                                        <i class="fa-solid fa-plus"></i> Add
                                    </button>
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <?php if (empty($srv['charges'])): ?>
                                        <div style="font-size: 12px; color: var(--text2); font-style: italic;">None (Base fee only)</div>
                                    <?php else: ?>
                                        <?php foreach ($srv['charges'] as $chg): ?>
                                            <div style="background: var(--panel-bg); border: 1px solid var(--border); border-radius: 6px; padding: 5px 8px; display: flex; justify-content: space-between; align-items: center;">
                                                <div>
                                                    <span style="font-weight: 700; font-size: 12px; color: var(--text);"><?= htmlspecialchars($chg['name']) ?></span>
                                                    <span style="font-size: 11.5px; color: var(--accent); font-weight: 700; margin-left: 4px;">+PHP <?= number_format((float)$chg['price'], 2) ?></span>
                                                </div>
                                                <div style="display: flex; gap: 3px;">
                                                    <button class="btn-icon-sq" 
                                                            style="width: 22px; height: 22px;" 
                                                            data-id="<?= (int)$chg['id'] ?>"
                                                            data-name="<?= htmlspecialchars($chg['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-price="<?= (float)$chg['price'] ?>"
                                                            onclick="openEditChargeModal(this)" 
                                                            title="Edit Price">
                                                        <i class="fa-solid fa-pen" style="font-size: 11px;"></i>
                                                    </button>
                                                    <form method="POST" action="dashboard.php" style="margin:0;" onsubmit="return confirm('Remove this add-on?');">
                                                        <input type="hidden" name="form_section" value="services">
                                                        <input type="hidden" name="service_action" value="remove_charge">
                                                        <input type="hidden" name="csrf" value="<?= $csrf ?>">
                                                        <input type="hidden" name="charge_id" value="<?= (int)$chg['id'] ?>">
                                                        <button type="submit" class="btn-icon-sq" style="width: 22px; height: 22px; color: #ef4444;" title="Remove Charge">
                                                            <i class="fa-solid fa-trash" style="font-size: 11px;"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>

</div>

<!-- ══════════════════════ MODALS ══════════════════════ -->

<!-- Modal: Edit Variant Rate / Version Rate -->
<div id="modal-edit-variant-rate" class="modal-overlay">
    <div class="modal" style="max-width: 420px; border-radius: 12px;">
        <div class="modal-header">
            <h3 class="modal-title" id="mevr-title"><i class="fa-solid fa-pen-to-square"></i> Update Service Rate</h3>
            <button type="button" class="modal-close" onclick="closeModal('modal-edit-variant-rate')">&times;</button>
        </div>
        <form method="POST" action="dashboard.php">
            <input type="hidden" name="form_section" value="services">
            <input type="hidden" name="service_action" value="update_variant_price">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="variant_id" id="mevr-variant-id">
            
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 10px;">
                <div class="form-group">
                    <label class="form-label">Variant Name</label>
                    <input type="text" id="mevr-name" readonly class="form-control" style="background: var(--bg2);">
                </div>
                <div class="form-group">
                    <label class="form-label">New Rate (PHP)</label>
                    <input type="number" name="new_price" id="mevr-price" step="0.01" min="0" required class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Effective Date</label>
                    <input type="date" name="effective_date" id="mevr-eff-date" value="<?= date('Y-m-d') ?>" required class="form-control">
                </div>
                <div style="background: var(--bg2); padding: 8px 10px; border-radius: 6px; border: 1px solid var(--border); font-size: 0.75rem;">
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; margin: 0; font-weight: 600;">
                        <input type="checkbox" name="version_rate" value="1" checked>
                        <span>Preserve Rate Versioning</span>
                    </label>
                    <p style="color: var(--text2); margin: 4px 0 0 20px; font-size: 11.5px;">Existing bookings keep their original snapshot rates.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-gray" onclick="closeModal('modal-edit-variant-rate')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Rate</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Certificate Rate -->
<div id="modal-edit-cert-rate" class="modal-overlay">
    <div class="modal" style="max-width: 380px; border-radius: 12px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-stamp"></i> Edit Certificate Rate</h3>
            <button type="button" class="modal-close" onclick="closeModal('modal-edit-cert-rate')">&times;</button>
        </div>
        <form method="POST" action="dashboard.php">
            <input type="hidden" name="form_section" value="services">
            <input type="hidden" name="service_action" value="update_cert_rate">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="cert_id" id="mecr-id">
            
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 10px;">
                <div class="form-group">
                    <label class="form-label">Certificate Type</label>
                    <input type="text" id="mecr-name" readonly class="form-control" style="background: var(--bg2);">
                </div>
                <div class="form-group">
                    <label class="form-label">Price Per Copy (PHP)</label>
                    <input type="number" name="price_per_copy" id="mecr-price" step="0.01" min="0" required class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-gray" onclick="closeModal('modal-edit-cert-rate')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Price</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Add Variant -->
<div id="modal-add-variant" class="modal-overlay">
    <div class="modal" style="max-width: 440px; border-radius: 12px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-plus-circle"></i> Add Service Variant</h3>
            <button type="button" class="modal-close" onclick="closeModal('modal-add-variant')">&times;</button>
        </div>
        <form method="POST" action="dashboard.php">
            <input type="hidden" name="form_section" value="services">
            <input type="hidden" name="service_action" value="create_variant">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="service_id" id="mav-service-id">
            
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 10px;">
                <div class="form-group">
                    <label class="form-label">Variant Name (e.g., Outside Service, Truck, Special)</label>
                    <input type="text" name="name" required class="form-control" placeholder="Variant name">
                </div>
                <div class="form-group">
                    <label class="form-label">Fee / Price (PHP)</label>
                    <input type="number" name="price" step="0.01" min="0" required class="form-control" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Schedule / Service Type</label>
                    <input type="text" name="schedule_type" class="form-control" placeholder="e.g. Standard, Special, By Appointment" value="Standard">
                </div>
                <div class="form-group">
                    <label class="form-label">Included Sponsors (if sacrament)</label>
                    <input type="number" name="included_sponsors" min="0" value="0" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Description (optional)</label>
                    <input type="text" name="description" class="form-control" placeholder="Brief note">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-gray" onclick="closeModal('modal-add-variant')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Variant</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Add Requirement -->
<div id="modal-add-requirement" class="modal-overlay">
    <div class="modal" style="max-width: 420px; border-radius: 12px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-list-check"></i> Add Service Requirement</h3>
            <button type="button" class="modal-close" onclick="closeModal('modal-add-requirement')">&times;</button>
        </div>
        <form method="POST" action="dashboard.php">
            <input type="hidden" name="form_section" value="services">
            <input type="hidden" name="service_action" value="add_requirement">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="service_id" id="mar-service-id">
            
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 10px;">
                <div class="form-group">
                    <label class="form-label">Requirement Title</label>
                    <input type="text" name="requirement" required class="form-control" placeholder="e.g. 1 Xerox Copy of PSA Birth Certificate">
                </div>
                <div class="form-group">
                    <label class="form-label">Guideline / Instructions</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Optional details for requester"></textarea>
                </div>
                <div class="form-group" style="margin: 0;">
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.8rem;">
                        <input type="checkbox" name="is_required" value="1" checked>
                        <span>Mandatory requirement</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-gray" onclick="closeModal('modal-add-requirement')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Requirement</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Add Additional Charge -->
<div id="modal-add-charge" class="modal-overlay">
    <div class="modal" style="max-width: 400px; border-radius: 12px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-circle-plus"></i> Add Charge</h3>
            <button type="button" class="modal-close" onclick="closeModal('modal-add-charge')">&times;</button>
        </div>
        <form method="POST" action="dashboard.php">
            <input type="hidden" name="form_section" value="services">
            <input type="hidden" name="service_action" value="add_charge">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="service_id" id="mac-service-id">
            
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 10px;">
                <div class="form-group">
                    <label class="form-label">Charge Name (e.g. Choir, Dapit)</label>
                    <input type="text" name="name" required class="form-control" placeholder="Item name">
                </div>
                <div class="form-group">
                    <label class="form-label">Additional Fee (PHP)</label>
                    <input type="number" name="price" step="0.01" min="0" required class="form-control" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label class="form-label">Description (optional)</label>
                    <input type="text" name="description" class="form-control" placeholder="Details">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-gray" onclick="closeModal('modal-add-charge')">Cancel</button>
                <button type="submit" class="btn btn-primary">Add Charge</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Edit Additional Charge Price -->
<div id="modal-edit-charge" class="modal-overlay">
    <div class="modal" style="max-width: 380px; border-radius: 12px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-pen"></i> Edit Charge</h3>
            <button type="button" class="modal-close" onclick="closeModal('modal-edit-charge')">&times;</button>
        </div>
        <form method="POST" action="dashboard.php">
            <input type="hidden" name="form_section" value="services">
            <input type="hidden" name="service_action" value="update_charge_price">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="charge_id" id="mech-id">
            
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 10px;">
                <div class="form-group">
                    <label class="form-label">Charge Name</label>
                    <input type="text" id="mech-name" readonly class="form-control" style="background: var(--bg2);">
                </div>
                <div class="form-group">
                    <label class="form-label">Price (PHP)</label>
                    <input type="number" name="price" id="mech-price" step="0.01" min="0" required class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-gray" onclick="closeModal('modal-edit-charge')">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Price</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Service Metadata / Settings -->
<div id="modal-service-meta" class="modal-overlay">
    <div class="modal" style="max-width: 460px; border-radius: 12px;">
        <div class="modal-header">
            <h3 class="modal-title"><i class="fa-solid fa-gear"></i> Service Settings</h3>
            <button type="button" class="modal-close" onclick="closeModal('modal-service-meta')">&times;</button>
        </div>
        <form method="POST" action="dashboard.php">
            <input type="hidden" name="form_section" value="services">
            <input type="hidden" name="service_action" value="update_metadata">
            <input type="hidden" name="csrf" value="<?= $csrf ?>">
            <input type="hidden" name="service_id" id="msm-id">
            
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 10px;">
                <div class="form-group">
                    <label class="form-label">Service Title</label>
                    <input type="text" name="name" id="msm-name" required class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" id="msm-desc" rows="2" class="form-control"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Reference Source</label>
                    <input type="text" name="reference_source" id="msm-ref" class="form-control" placeholder="e.g. Cathedral Synod December 16, 2012">
                </div>
                <div class="form-group">
                    <label class="form-label">Effective Date</label>
                    <input type="date" name="effective_date" id="msm-eff" class="form-control">
                </div>
                <div class="form-group" style="margin: 0;">
                    <label style="display: flex; align-items: center; gap: 6px; cursor: pointer; font-size: 0.8rem;">
                        <input type="checkbox" name="is_reference_only" id="msm-is-ref" value="1">
                        <span>Mark as Reference Baseline Rate (Requires admin verification before treating as official current rate)</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-gray" onclick="closeModal('modal-service-meta')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditPriceModal(variantIdOrEl, name, price, effDate) {
    let variantId = variantIdOrEl;
    if (variantIdOrEl instanceof HTMLElement) {
        variantId = variantIdOrEl.getAttribute('data-id');
        name = variantIdOrEl.getAttribute('data-name');
        price = variantIdOrEl.getAttribute('data-price');
        effDate = variantIdOrEl.getAttribute('data-eff-date');
    }
    const idEl = document.getElementById('mevr-variant-id');
    if (idEl) idEl.value = variantId || '';
    const nameEl = document.getElementById('mevr-name');
    if (nameEl) nameEl.value = name || '';
    const priceEl = document.getElementById('mevr-price');
    if (priceEl) priceEl.value = price ? parseFloat(price).toFixed(2) : '0.00';
    const effEl = document.getElementById('mevr-eff-date');
    if (effEl && effDate) effEl.value = effDate;
    openModal('modal-edit-variant-rate');
}

function openCertRateModal(idOrEl, name, price) {
    let id = idOrEl;
    if (idOrEl instanceof HTMLElement) {
        id = idOrEl.getAttribute('data-id');
        name = idOrEl.getAttribute('data-name');
        price = idOrEl.getAttribute('data-price');
    }
    const idEl = document.getElementById('mecr-id');
    if (idEl) idEl.value = id || '';
    const nameEl = document.getElementById('mecr-name');
    if (nameEl) nameEl.value = name || '';
    const priceEl = document.getElementById('mecr-price');
    if (priceEl) priceEl.value = price ? parseFloat(price).toFixed(2) : '0.00';
    openModal('modal-edit-cert-rate');
}

function openAddVariantModal(serviceIdOrEl, srvName) {
    let serviceId = serviceIdOrEl;
    if (serviceIdOrEl instanceof HTMLElement) {
        serviceId = serviceIdOrEl.getAttribute('data-id');
    }
    const idEl = document.getElementById('mav-service-id');
    if (idEl) idEl.value = serviceId || '';
    openModal('modal-add-variant');
}

function openAddReqModal(serviceIdOrEl, srvName) {
    let serviceId = serviceIdOrEl;
    if (serviceIdOrEl instanceof HTMLElement) {
        serviceId = serviceIdOrEl.getAttribute('data-id');
    }
    const idEl = document.getElementById('mar-service-id');
    if (idEl) idEl.value = serviceId || '';
    openModal('modal-add-requirement');
}

function openAddChargeModal(serviceIdOrEl, srvName) {
    let serviceId = serviceIdOrEl;
    if (serviceIdOrEl instanceof HTMLElement) {
        serviceId = serviceIdOrEl.getAttribute('data-id');
    }
    const idEl = document.getElementById('mac-service-id');
    if (idEl) idEl.value = serviceId || '';
    openModal('modal-add-charge');
}

function openEditChargeModal(chargeIdOrEl, name, price) {
    let chargeId = chargeIdOrEl;
    if (chargeIdOrEl instanceof HTMLElement) {
        chargeId = chargeIdOrEl.getAttribute('data-id');
        name = chargeIdOrEl.getAttribute('data-name');
        price = chargeIdOrEl.getAttribute('data-price');
    }
    const idEl = document.getElementById('mech-id');
    if (idEl) idEl.value = chargeId || '';
    const nameEl = document.getElementById('mech-name');
    if (nameEl) nameEl.value = name || '';
    const priceEl = document.getElementById('mech-price');
    if (priceEl) priceEl.value = price ? parseFloat(price).toFixed(2) : '0.00';
    openModal('modal-edit-charge');
}

function openServiceMetaModal(idOrEl, name, desc, ref, eff, isRef) {
    let id = idOrEl;
    if (idOrEl instanceof HTMLElement) {
        id = idOrEl.getAttribute('data-id');
        name = idOrEl.getAttribute('data-name');
        desc = idOrEl.getAttribute('data-desc');
        ref = idOrEl.getAttribute('data-ref');
        eff = idOrEl.getAttribute('data-eff');
        isRef = idOrEl.getAttribute('data-is-ref');
    }
    const idEl = document.getElementById('msm-id');
    if (idEl) idEl.value = id || '';
    const nameEl = document.getElementById('msm-name');
    if (nameEl) nameEl.value = name || '';
    const descEl = document.getElementById('msm-desc');
    if (descEl) descEl.value = desc || '';
    const refEl = document.getElementById('msm-ref');
    if (refEl) refEl.value = ref || '';
    const effEl = document.getElementById('msm-eff');
    if (effEl) effEl.value = eff || '';
    const isRefEl = document.getElementById('msm-is-ref');
    if (isRefEl) isRefEl.checked = (parseInt(isRef) === 1);
    openModal('modal-service-meta');
}

function filterAdminServiceCategory(cat, btn) {
    document.querySelectorAll('#admin-service-top-tabs .srv-top-tab').forEach(t => {
        const isTarget = (t === btn);
        t.classList.toggle('active', isTarget);
        t.style.background = isTarget ? '#111111' : 'var(--surface)';
        t.style.color = isTarget ? '#FFFFFF' : 'var(--text)';
        t.style.borderColor = isTarget ? '#111111' : 'var(--border)';
        const icon = t.querySelector('i');
        if (icon) icon.style.color = isTarget ? '#FFFFFF' : (t.dataset.cat === 'all' ? '#FFFFFF' : 'var(--accent)');
    });
    document.querySelectorAll('.srv-category-section').forEach(sec => {
        const secCat = sec.getAttribute('data-cat') || '';
        sec.style.display = (cat === 'all' || secCat === cat) ? '' : 'none';
    });
}
window.filterAdminServiceCategory = filterAdminServiceCategory;
window.openEditPriceModal = openEditPriceModal;
window.openCertRateModal = openCertRateModal;
window.openAddVariantModal = openAddVariantModal;
window.openAddReqModal = openAddReqModal;
window.openAddChargeModal = openAddChargeModal;
window.openEditChargeModal = openEditChargeModal;
window.openServiceMetaModal = openServiceMetaModal;
</script>
