<?php

declare(strict_types=1);

use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment;

require __DIR__ . '/vendor/autoload.php';

class Simple
{
    private const REDIRECT_URL = "http://localhost:8080/?redirect";
    private const PRICE = 2500;

    private MollieApiClient $mollie;

    public function __construct()
    {
        $this->mollie = new MollieApiClient();
        $this->mollie->setApiKey($_ENV['MOLLIE_AP']);
    }

    public function createOrder(string $email)
    {
        $payment = $this->mollie->payments->create([
            "amount" => [
                "currency" => "EUR",
                "value" => number_format(self::PRICE, 2, '.', ''),
            ],
            "description" => sprintf("Sign-up from %s", $email),
            "redirectUrl" => self::REDIRECT_URL,
        ]);

        $orderId = $this->store($payment);

        if ($orderId !== null) {
            $_SESSION['pending'] = $orderId;

            header("Location: " . $payment->getCheckoutUrl(), true, 303);
        }
    }

    private function store(Payment $payment): ?string
    {
        $db = new PDO("sqlite:".__DIR__."/database.sql");
        $query = $db->prepare(
                "INSERT INTO `order` (payment_id, status, created_on) VALUES (?, ?, datetime('now'))"
        );
        $query->execute([$payment->id, 'open']);

        if (!$db->lastInsertId()) {
            return null;
        }

        return $db->lastInsertId();
    }

    public function update(string $orderId): void
    {
        $db = new PDO("sqlite:".__DIR__."/database.sql");
        $query = $db->prepare(
            "SELECT payment_id FROM `order` WHERE id = ?"
        );
        $query->execute([$orderId]);
        $result = $query->fetchAll();

        if (!array_key_exists(0, $result)) {
            return;
        }

        $order = $result[0];

        if ($order['status'] !== 'open') {
            return;
        }

        $payment = $this->mollie->payments->get($order['payment_id']);

        if ($payment->isPaid()) {
            $status = 'paid';
        } else if ($payment->isFailed()) {
            $status = 'failed';
        } else if ($payment->isCanceled()) {
            $status = 'cancelled';
        } else if ($payment->isPending()) {
            $status = 'pending';
        } else {
            $status = 'open';
        }

        $query = $db->prepare(
            "UPDATE `order` SET status = ? WHERE id = ?"
        );
        $query->execute([$status, $orderId]);
    }
}

session_start();
$simple = new Simple();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title></title>
    <meta name="description" content="">
    <meta name="author" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="data:,">
    <style></style>
</head>
<body>
    <header>

    </header>
    <main>
        <h1>Course title</h1>
        <small>€25.00</small>
        <p>This is the course description.</p>
        <?php
        if (isset($_GET['redirect']) &&
            isset($_SESSION['pending'])) {
            $simple->update($_SESSION['pending']);
            echo "<p>Welcome to the course</p>";
        }

        if (isset($_POST['email'])) {
            $simple->createOrder($_POST['email']);
        }
        ?>
        <form method="POST">
            <label>Email
                <input type="email" name="email" required="required">
            </label>
            <button>Sign up</button>
        </form>
    </main>
</body>
</html>




