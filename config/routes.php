<?php

declare(strict_types=1);

/**
 * config/routes.php — Definisi seluruh route aplikasi Campus Find
 *
 * Format: $router->get('/path', 'ControllerClass@method')
 *         $router->post('/path', 'ControllerClass@method')
 */

// ──────────────────────────────────────────────
// 1. PUBLIC ROUTES
// ──────────────────────────────────────────────
$router->get('/', 'HomeController@index');
$router->get('/cara-kerja', 'HomeController@caraKerja');
$router->get('/search', 'SearchController@index');

// Layanan File Uploads Terproteksi (Foto Barang & Bukti Klaim)
$router->get('/uploads/{type}/{file}', 'FileController@serveUpload');

// ──────────────────────────────────────────────
// 2. AUTHENTICATION ROUTES (Prompt 20B)
// ──────────────────────────────────────────────
$router->get('/login', 'AuthController@loginForm');
$router->post('/login', 'AuthController@login');
$router->get('/logout', 'AuthController@logout');
$router->post('/logout', 'AuthController@logout');

// Aktivasi Akun Civitas (OTP Email)
$router->get('/activate', 'AuthController@activateForm');
$router->post('/activate', 'AuthController@sendActivationOtp');
$router->get('/activate/verify', 'AuthController@activateVerifyForm');
$router->post('/activate/verify', 'AuthController@processActivation');

// Lupa Kata Sandi (OTP Email)
$router->get('/forgot-password', 'AuthController@forgotPasswordForm');
$router->post('/forgot-password', 'AuthController@sendForgotPasswordOtp');
$router->get('/forgot-password/verify', 'AuthController@forgotPasswordVerifyForm');
$router->post('/forgot-password/verify', 'AuthController@processForgotPassword');

// Kirim Ulang OTP (Jeda 60 detik)
$router->post('/auth/resend-otp', 'AuthController@resendOtp');

// Ganti Kata Sandi Wajib (Mandatory Temporary Password)
$router->get('/change-password-mandatory', 'AuthController@mandatoryChangePasswordForm');
$router->post('/change-password-mandatory', 'AuthController@processMandatoryChangePassword');

// ──────────────────────────────────────────────
// 3. GENERAL AUTHENTICATED ROUTES
// ──────────────────────────────────────────────
$router->get('/dashboard', 'DashboardController@index');
$router->get('/profile', 'ProfileController@index');
$router->post('/profile/password', 'ProfileController@updatePassword');
$router->post('/profile/notifications', 'ProfileController@updateNotificationPreferences');
$router->post('/profile/phone', 'ProfileController@updatePhone');
$router->post('/profile/email/request-otp', 'ProfileController@requestEmailChangeOtp');
$router->post('/profile/email/verify-otp', 'ProfileController@verifyEmailChangeOtp');
$router->post('/profile/email/cancel', 'ProfileController@cancelEmailChange');
$router->post('/profile/email/verify/request-otp', 'ProfileController@requestCurrentEmailVerificationOtp');
$router->post('/profile/email/verify/confirm', 'ProfileController@verifyCurrentEmailOtp');
$router->post('/profile/email/verify/cancel', 'ProfileController@cancelCurrentEmailVerification');

// ──────────────────────────────────────────────
// 4. LOST REPORT ROUTES (Fitur Laporan Kehilangan)
// ──────────────────────────────────────────────
// 4. REPORT MANAGEMENT ROUTES (Petugas & Admin — Prompt 33B)
// ──────────────────────────────────────────────
$router->get('/reports/manage', 'ReportManagementController@index');
$router->get('/reports', 'ReportManagementController@index');
$router->post('/reports/lost/{id}/status', 'ReportManagementController@updateLostStatus');
$router->post('/admin/reports/{type}/{id}/delete', 'ReportManagementController@adminDelete');

// ──────────────────────────────────────────────
// 4A. LOST REPORT ROUTES (Katalog & CRUD Publik/Civitas)
// ──────────────────────────────────────────────
$router->get('/reports/lost', 'LostReportController@index');
$router->post('/reports/lost', 'LostReportController@store');
$router->get('/reports/lost/create', 'LostReportController@create');
$router->post('/reports/lost/create', 'LostReportController@store');
$router->get('/reports/lost/{id}', 'LostReportController@show');
$router->get('/reports/lost/{id}/edit', 'LostReportController@edit');
$router->post('/reports/lost/{id}/edit', 'LostReportController@update');
$router->post('/reports/lost/{id}/delete', 'LostReportController@delete');

// ──────────────────────────────────────────────
// 5. FOUND REPORT ROUTES (Fitur Laporan Penemuan)
// ──────────────────────────────────────────────
$router->get('/reports/found', 'FoundReportController@index');
$router->post('/reports/found', 'FoundReportController@store');
$router->get('/reports/found/create', 'FoundReportController@create');
$router->post('/reports/found/create', 'FoundReportController@store');
$router->get('/reports/found/{id}', 'FoundReportController@show');
$router->get('/reports/found/{id}/edit', 'FoundReportController@edit');
$router->post('/reports/found/{id}/edit', 'FoundReportController@update');
$router->post('/reports/found/{id}/handover', 'FoundReportController@handover');
$router->post('/reports/found/{id}/delete', 'FoundReportController@delete');

// ──────────────────────────────────────────────
// 6. CLAIMS & VERIFICATION ROUTES (Prompt 9)
// ──────────────────────────────────────────────
$router->get('/claims/create', 'ClaimController@create');
$router->post('/claims/create', 'ClaimController@store');
$router->post('/claims', 'ClaimController@store');
$router->get('/claims/{id}', 'ClaimController@show');
$router->get('/civitas/my-claims', 'ClaimController@myClaims');

// Petugas claim verification workspace & handover
$router->get('/petugas/claims', 'PetugasClaimController@index');
$router->get('/petugas/claims/{id}', 'PetugasClaimController@show');
$router->post('/petugas/claims/{id}/review', 'PetugasClaimController@review');
$router->post('/petugas/claims/{id}/request-info', 'PetugasClaimController@requestInfo');
$router->post('/petugas/claims/{id}/approve', 'PetugasClaimController@approve');
$router->post('/petugas/claims/{id}/reject', 'PetugasClaimController@reject');
$router->get('/petugas/claims/{id}/handover', 'PetugasClaimController@handover');
$router->post('/petugas/claims/{id}/handover', 'PetugasClaimController@processHandover');

// ──────────────────────────────────────────────
// 7. ADMIN ROLE-PROTECTED ROUTES (Super User)
// ──────────────────────────────────────────────
$router->get('/admin', 'AdminController@dashboard');
$router->get('/admin/dashboard', 'AdminController@dashboard');

// User Management
$router->get('/admin/users', 'AdminController@users');
$router->post('/admin/users/create', 'AdminController@createUser');
$router->post('/admin/users/{id}/role', 'AdminController@updateUserRole');
$router->post('/admin/users/{id}/status', 'AdminController@updateUserStatus');
$router->post('/admin/users/{id}/temp-password', 'AdminController@setTempPassword');
$router->post('/admin/users/{id}/resend-activation', 'AdminController@resendActivation');
$router->post('/admin/users/bulk-action', 'AdminController@bulkUserAction');
$router->get('/admin/users/download-temp-passwords-csv', 'AdminController@downloadBulkTempPasswordsCsv');
$router->post('/admin/users/{id}/edit', 'AdminController@updateUserData');
$router->post('/admin/users/{id}/delete', 'AdminController@deleteUserPermanently');
$router->post('/admin/users/{id}/permanent-delete', 'AdminController@deleteUserPermanently');
$router->post('/admin/users/{id}/mark-verified', 'AdminController@markEmailVerifiedLocal');
$router->get('/admin/users/import', 'AdminController@importCivitas');
$router->get('/admin/users/import/template', 'AdminController@downloadCivitasTemplate');
$router->post('/admin/users/import/preview', 'AdminController@uploadCivitasImport');
$router->post('/admin/users/import/confirm', 'AdminController@confirmCivitasImport');
$router->post('/admin/users/import/cancel', 'AdminController@cancelCivitasImport');
$router->get('/admin/users/import/report', 'AdminController@downloadCivitasImportReport');

// Master Data — Categories
$router->get('/admin/categories', 'AdminController@categories');
$router->post('/admin/categories/create', 'AdminController@createCategory');
$router->post('/admin/categories/{id}/update', 'AdminController@updateCategory');
$router->post('/admin/categories/{id}/toggle', 'AdminController@toggleCategory');

// Master Data — Campus Locations
$router->get('/admin/locations', 'AdminController@locations');
$router->post('/admin/locations/create', 'AdminController@createLocation');
$router->post('/admin/locations/{id}/update', 'AdminController@updateLocation');
$router->post('/admin/locations/{id}/toggle', 'AdminController@toggleLocation');

// Master Data — Storage Locations
$router->get('/admin/storage-locations', 'AdminController@storageLocations');
$router->post('/admin/storage-locations/create', 'AdminController@createStorageLocation');
$router->post('/admin/storage-locations/{id}/update', 'AdminController@updateStorageLocation');
$router->post('/admin/storage-locations/{id}/toggle', 'AdminController@toggleStorageLocation');

// Audit Logs
$router->get('/admin/audit-logs', 'AdminController@auditLogs');

// Riwayat Chat AI (Prompt 36C)
$router->get('/admin/chat-history', 'AdminController@cfindHistory');
$router->get('/admin/chat-history/{id}', 'AdminController@cfindHistoryDetail');

// System Settings & Unified AI Configuration Center (Task 14)
$router->get('/admin/settings', 'AdminController@settings');
$router->post('/admin/settings', 'AdminController@updateSettings');
$router->get('/admin/ai/models', 'AdminController@getAiModels');
$router->post('/admin/ai/models', 'AdminController@getAiModels');
$router->post('/admin/ai/provider/save', 'AdminController@saveAiProviderConnection');
$router->post('/admin/ai/provider/delete-key', 'AdminController@deleteAiProviderKey');
$router->post('/admin/ai/provider/test', 'AdminController@testAiProviderConnection');
$router->post('/admin/ai/provider/custom/add', 'AdminController@addCustomAiProvider');
$router->post('/admin/ai/provider/custom/delete', 'AdminController@deleteCustomAiProvider');
$router->post('/admin/ai/default/save', 'AdminController@saveAiDefaultAssignment');
$router->post('/admin/ai/default/test', 'AdminController@testAiDefaultConnection');
$router->post('/admin/ai/feature/save', 'AdminController@saveAiFeatureAssignment');
$router->post('/admin/ai/feature/test', 'AdminController@testAiFeatureConfiguration');
$router->post('/admin/ai/feature/revert-default', 'AdminController@revertAiFeatureToDefault');
$router->post('/admin/settings/reset-cfind-budget', 'AdminController@resetCfindBudget');

// Notification Settings & Outbox Management (Prompt 20F)
$router->get('/admin/notification-settings', 'AdminController@notificationSettings');
$router->post('/admin/notification-settings', 'AdminController@updateNotificationSettings');
$router->post('/admin/notification-settings/test-connection', 'AdminController@testSmtpConnection');
$router->post('/admin/notification-settings/test-email', 'AdminController@sendTestEmail');
$router->post('/admin/notification-settings/retry/{id}', 'AdminController@retryOutboxItem');
$router->post('/admin/notification-settings/reset-circuit', 'AdminController@resetCircuitBreaker');
$router->post('/admin/notification-settings/process-now', 'AdminController@processOutboxNow');
$router->post('/admin/notification-settings/allowlist/add-user', 'AdminController@addRecipientToAllowlist');

// ──────────────────────────────────────────────
// 8. PETUGAS ROLE-PROTECTED ROUTES (L&F Center)
// ──────────────────────────────────────────────
$router->get('/petugas/inventory', 'PetugasController@inventory');
$router->get('/petugas/handover', 'PetugasController@pendingHandover');
$router->get('/petugas/found-reports', 'PetugasController@foundReports');
$router->get('/petugas/found-reports/{id}/verify', 'PetugasController@verifyForm');
$router->post('/petugas/found-reports/{id}/verify', 'PetugasController@verifyStore');
$router->get('/petugas/intake/{id}', 'PetugasController@intakeForm');
$router->post('/petugas/intake/{id}', 'PetugasController@intakeStore');
$router->get('/petugas/items/{id}', 'PetugasController@itemDetail');
$router->post('/petugas/items/{id}/status', 'PetugasController@updatePhysicalStatus');
$router->post('/petugas/items/{id}/relocate', 'PetugasController@relocateStorage');
$router->get('/petugas/storage-locations', 'PetugasController@storageLocations');
$router->post('/petugas/storage-locations/create', 'PetugasController@storageStore');

// ──────────────────────────────────────────────
// 9. CIVITAS ROLE-PROTECTED ROUTES (Mahasiswa/Staff)
// ──────────────────────────────────────────────
$router->get('/civitas/my-reports', 'CivitasController@myReports');

// ──────────────────────────────────────────────
// 10. INTERNAL CHAT ROUTES (Prompt 10)
// ──────────────────────────────────────────────
$router->get('/chats', 'ChatController@index');
$router->get('/chats/unread-count', 'ChatController@unreadCount');
$router->get('/chats/claim/{id}', 'ChatController@initClaimChat');
$router->get('/chats/report/{type}/{id}', 'ChatController@initReportChat');
$router->get('/chats/{id}', 'ChatController@room');
$router->post('/chats/{id}/messages', 'ChatController@sendMessage');
$router->get('/chats/{id}/poll', 'ChatController@poll');

// ──────────────────────────────────────────────
// 11. IN-APP NOTIFICATION ROUTES (Prompt 11)
// ──────────────────────────────────────────────
$router->get('/notifications', 'NotificationController@index');
$router->get('/notifications/recent', 'NotificationController@recent');
$router->get('/notifications/unread-count', 'NotificationController@unreadCount');
$router->post('/notifications/{id}/read', 'NotificationController@markAsRead');
$router->post('/notifications/read-all', 'NotificationController@markAllAsRead');

// ──────────────────────────────────────────────
// 12. NVIDIA NIM AI INTEGRATION ROUTES (Prompt 12)
// ──────────────────────────────────────────────
$router->get('/ai-match/lost/{id}', 'AiMatchController@viewCandidatesForLost');
$router->get('/ai-match/found/{id}', 'AiMatchController@viewCandidatesForFound');
$router->post('/ai-match/lost/{id}/run', 'AiMatchController@runForLost');
$router->post('/ai-match/found/{id}/run', 'AiMatchController@runForFound');
$router->post('/ai-match/{id}/status', 'AiMatchController@updateStatus');

// ──────────────────────────────────────────────
// 13. CHATBOT C-FIND ROUTES (Prompt 18)
// ──────────────────────────────────────────────
$router->post('/api/cfind/chat', 'CFindController@chat');
$router->post('/api/cfind/reset', 'CFindController@reset');
$router->get('/api/cfind/status', 'CFindController@status');
$router->get('/api/cfind/history', 'CFindController@history');




