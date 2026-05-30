<?php
/**
 * create_service_records.php
 * 
 * Shared helper — creates jobs, installation_requests, finishing_requests
 * for a business user after wizard completion.
 * 
 * Called from:
 * - paymob_callback.php (after payment for equipment users)
 * - auth/signup.php (for non-equipment users with zero order)
 */

function create_service_records($conn, $businessId, $orderId, $wizardSession) {
    if (!$businessId || !$conn) return;

    $w        = $wizardSession;
    $services = $w['services'] ?? [];

    // Get job location
    $jobLocation = "";
    $bizLocRes = pg_query_params($conn,
        "SELECT location_text FROM businesses WHERE user_id = $1 LIMIT 1",
        [$businessId]);
    if ($bizLocRes && pg_num_rows($bizLocRes) > 0) {
        $jobLocation = trim((string)(pg_fetch_assoc($bizLocRes)["location_text"] ?? ""));
    }
    if ($jobLocation === "") $jobLocation = "Business Location";

    // ── JOBS (staff) ──────────────────────────────────────────────
    if (in_array('staff', $services)) {
        $laborMap = [];
        foreach (['waiter','chef','cashier','security','barista','busboy','host','kitchen_helper'] as $role) {
            $qty = (int)($w[$role . '_count'] ?? 0);
            if ($qty > 0) $laborMap[$role] = $qty;
        }

        if (!empty($laborMap)) {
            $insJobSql = "
                INSERT INTO jobs
                (business_id, title, description, location, salary_amount, compensation_type, status, price, worker_id, job_type, labor_role)
                VALUES ($1, $2, $3, $4, $5, $6, 'available', 0, NULL, 'labor', $7)
            ";

            foreach ($laborMap as $role => $qty) {
                for ($i = 1; $i <= $qty; $i++) {
                    $roleLabel   = ucfirst(str_replace("_", " ", $role));
                    $title       = $roleLabel . " Needed";
                    $description = $roleLabel . " requested during setup.";

                    pg_query_params($conn, $insJobSql, [
                        $businessId,
                        $title,
                        $description,
                        $jobLocation,
                        0,
                        'monthly',
                        strtolower(str_replace(" ", "_", $role))
                    ]);
                }
            }
        }
    }

    // ── INSTALLATION REQUESTS ─────────────────────────────────────
    if (in_array('installation', $services)) {
        $installationServices = $w['installation_services'] ?? [];

        if (!empty($installationServices)) {
            $insInstallSql = "
                INSERT INTO installation_requests
                (user_id, order_id, services, status, company_id, total_price)
                VALUES ($1, $2, $3, 'pending', NULL, 0)
                ON CONFLICT (user_id, services) DO NOTHING
            ";

            foreach ($installationServices as $service) {
                $service = trim((string)$service);
                if ($service === "") continue;
                pg_query_params($conn, $insInstallSql, [
                    $businessId,
                    $orderId,
                    "{" . $service . "}",
                ]);
            }
        }
    }

    // ── FINISHING REQUEST ─────────────────────────────────────────
    if (in_array('finishing', $services)) {
        $areaSqm = (int)($w['area_sqm'] ?? 0);
        if ($areaSqm === 0) {
            $bizAreaRes = pg_query_params($conn,
                "SELECT area_sqm FROM businesses WHERE user_id = $1 LIMIT 1",
                [$businessId]);
            if ($bizAreaRes && pg_num_rows($bizAreaRes) > 0) {
                $areaSqm = (int)(pg_fetch_assoc($bizAreaRes)["area_sqm"] ?? 0);
            }
        }

        pg_query_params($conn, "
            INSERT INTO finishing_requests
            (user_id, order_id, area_sqm, finishing_types, status, company_id, total_price)
            VALUES ($1, $2, $3, NULL, 'pending', NULL, 0)
            ON CONFLICT (user_id, order_id) DO NOTHING
        ", [
            $businessId,
            $orderId,
            $areaSqm > 0 ? $areaSqm : null,
        ]);
    }

    // ── Mark business setup as completed ──────────────────────────
    pg_query_params($conn,
        "UPDATE businesses SET setup_status = 'completed', updated_at = now() WHERE user_id = $1",
        [$businessId]);
}