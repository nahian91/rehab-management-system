<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<style>
    .arms-staff-wrapper { padding: 24px; background: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; color: #0f172a; max-width: 1300px; margin: 20px auto; box-sizing: border-box; }
    .arms-staff-wrapper * { box-sizing: border-box; }
    .arms-subnav-bar { display: flex; gap: 8px; border-bottom: 2px solid #e2e8f0; padding-bottom: 0; margin-bottom: 24px; }
    .arms-subnav-link { padding: 10px 20px; text-decoration: none; color: #64748b; font-weight: 600; font-size: 13px; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.15s ease; }
    .arms-subnav-link:hover { color: #4f46e5; }
    .arms-subnav-link.active { color: #4f46e5; border-bottom-color: #4f46e5; }
    .arms-card-box { background: #ffffff; border: 1px solid #e2e8f0; border-radius: 10px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.02); }
    .arms-card-header-flex { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; margin-bottom: 20px; }
    .arms-form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 24px; }
    .arms-form-group { display: flex; flex-direction: column; gap: 6px; }
    .arms-form-group label { font-size: 12px; font-weight: 600; color: #334155; text-transform: uppercase; letter-spacing: 0.02em; }
    .arms-form-group input, .arms-form-group select { padding: 10px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; color: #0f172a; background-color: #fff; width: 100%; }
    .arms-search-input-field { max-width: 260px; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 13px; }
    .arms-submit-btn { background: #4f46e5; color: #fff; border: none; padding: 12px 24px; font-size: 13px; font-weight: 600; border-radius: 6px; cursor: pointer; text-decoration: none; display: inline-block; }
    .arms-submit-btn:hover { background: #4338ca; }
    .arms-table-container { overflow-x: auto; }
    .arms-data-table { width: 100%; border-collapse: collapse; text-align: left; }
    .arms-data-table th { background: #f8fafc; padding: 12px 16px; font-size: 11px; font-weight: 600; text-transform: uppercase; color: #64748b; border-bottom: 2px solid #e2e8f0; }
    .arms-data-table td { padding: 14px 16px; font-size: 13px; border-bottom: 1px solid #f1f5f9; color: #334155; vertical-align: middle; }
    .arms-data-table tr:hover td { background: #f8fafc; }
    .arms-staff-profile-meta { display: flex; align-items: center; gap: 12px; }
    .arms-staff-avatar { width: 40px; height: 40px; border-radius: 50%; object-fit: cover; background: #e2e8f0; border: 1px solid #cbd5e1; }
    .arms-avatar-fallback { width: 40px; height: 40px; border-radius: 50%; background: #e2e8f0; display: flex; align-items: center; justify-content: center; color: #64748b; font-weight: bold; }
    .arms-role-badge { display: inline-block; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; text-transform: capitalize; }
    .badge-doctor { background: #e0f2fe; color: #0369a1; }
    .badge-physio { background: #fae8ff; color: #a21caf; }
    .badge-nurse { background: #dcfce7; color: #15803d; }
    .badge-accountant { background: #fef9c3; color: #a16207; }
    .badge-support { background: #f1f5f9; color: #475569; }
    .arms-status-dot { display: inline-flex; align-items: center; gap: 6px; font-weight: 500; }
    .arms-status-dot::before { content: ''; width: 8px; height: 8px; border-radius: 50%; }
    .status-active::before { background: #10b981; }
    .status-inactive::before { background: #ef4444; }
    .arms-action-btn-group { display: flex; gap: 6px; align-items: center; }
    .arms-action-btn { padding: 5px 10px; font-size: 12px; border-radius: 4px; text-decoration: none; font-weight: 500; display: inline-flex; align-items: center; justify-content: center; }
    .btn-view { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
    .btn-edit { background: #eff6ff; color: #2563eb; border: 1px solid #bfdbfe; }
    .btn-delete { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
    .profile-view-grid { display: flex; gap: 30px; flex-wrap: wrap; margin-top: 15px; }
    .profile-view-sidebar { flex: 1; min-width: 240px; max-width: 320px; text-align: center; border-right: 1px solid #e2e8f0; padding-right: 30px; }
    .profile-large-avatar { width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 3px solid #4f46e5; margin-bottom: 15px; background: #f1f5f9; }
    .profile-large-fallback { width: 150px; height: 150px; border-radius: 50%; background: #4f46e5; color: #fff; font-size: 48px; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px auto; font-weight: bold; }
    .profile-view-details { flex: 2; min-width: 300px; }
    .profile-detail-row { display: flex; padding: 12px 0; border-bottom: 1px dashed #e2e8f0; justify-content: space-between; font-size: 14px; }
    .profile-detail-label { font-weight: 600; color: #64748b; }
    .profile-detail-val { color: #0f172a; font-weight: 500; }
</style>