<?php
define('ROLES', ['admin', 'doctor', 'nurse', 'patient', 'pharmacist']);

define('APPOINTMENT_STATUS', ['pending', 'confirmed', 'cancelled', 'completed']);

define('PRESCRIPTION_STATUS', ['created', 'verified', 'dispensed']);

define('BILLING_STATUS', ['pending', 'paid']);

define('HTTP_OK',                   200);
define('HTTP_CREATED',              201);
define('HTTP_BAD_REQUEST',          400);
define('HTTP_UNAUTHORIZED',         401);
define('HTTP_FORBIDDEN',            403);
define('HTTP_NOT_FOUND',            404);
define('HTTP_INTERNAL_SERVER_ERROR',500);