<?php
require_once __DIR__ . '/../Services/MessageService.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Helpers/Response.php';
require_once __DIR__ . '/../Helpers/Validator.php';

class MessageController {

    public static function create() {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['doctor', 'nurse']);

        $payload = json_decode(file_get_contents('php://input'), true);

        $v = new Validator($payload);
        $v->required('appointment_id')
          ->required('message');

        if ($v->fails()) Response::error(implode(', ', $v->errors()), 400);

        $result = MessageService::create($payload, (int) $auth['user_id']);
        Response::success('Note sent', $result, 201);
    }

    public static function list($appointmentId) {
        $auth = AuthMiddleware::handle();
        AuthMiddleware::allowRoles($auth, ['doctor', 'nurse']);

        $messages = MessageService::getByAppointment($appointmentId);
        Response::success('Notes fetched', $messages);
    }
}