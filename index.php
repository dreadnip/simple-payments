<?php

declare(strict_types=1);

use Mollie\Api\MollieApiClient;
use Mollie\Api\Resources\Payment;

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

session_start();

class Simple
{
    private const REDIRECT_URL = "http://localhost:8080/?redirect";
    private const PRICE_IN_CENTS = 2500;

    private MollieApiClient $mollie;
    private PDO $db;

    public function __construct()
    {
        $this->mollie = new MollieApiClient();
        $this->mollie->setApiKey($_ENV['MOLLIE_API_KEY']);
        $this->db = new PDO("sqlite:".__DIR__."/database.sql");
    }

    public function createOrder(string $email)
    {
        $payment = $this->mollie->payments->create([
            "amount" => [
                "currency" => "EUR",
                "value" => number_format(self::PRICE_IN_CENTS / 100, 2, '.', ''),
            ],
            "description" => sprintf("Sign-up from %s", $email),
            "redirectUrl" => self::REDIRECT_URL,
        ]);

        $orderId = $this->store($payment, $email);

        if ($orderId !== null) {
            $_SESSION['pending'] = $orderId;

            header("Location: " . $payment->getCheckoutUrl(), true, 303);
        }
    }

    private function store(Payment $payment, string $email): ?string
    {
        $query = $this->db->prepare(
                "INSERT INTO `order` (payment_id, email, status, created_on) VALUES (?, ?, ?, datetime('now'))"
        );
        $query->execute([$payment->id, $email, 'open']);

        if (!$this->db->lastInsertId()) {
            return null;
        }

        return $this->db->lastInsertId();
    }

    public function check(string $email): bool
    {
        $query = $this->db->prepare(
            "SELECT id FROM `order` WHERE email = ?"
        );
        $query->execute([$email]);
        $result = $query->fetchAll();

        if (array_key_exists(0, $result)) {
            return true;
        }

        return false;
    }

    public function update(string $orderId): void
    {
        $query = $this->db->prepare(
            "SELECT payment_id, status FROM `order` WHERE id = ?"
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

        $query = $this->db->prepare(
            "UPDATE `order` SET status = ? WHERE id = ?"
        );
        $query->execute([$status, $orderId]);
    }
}

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
</head>
<body>
    <header>

    </header>
    <main>
        <h1>Course title</h1>
        <small>€25.00</small>
        <p>This is the course description.</p>
        <?php
        if (isset($_GET['redirect'])) {
            if (isset($_SESSION['pending'])) {
                $simple->update($_SESSION['pending']);
                echo "<aside>Welcome to the course!</aside>";
                unset($_SESSION['pending']);
            } else {
                header("Location: /", true, 303);
            }
        }

        if (isset($_POST['email'])) {
            if ($simple->check($_POST['email'])) {
                echo "<aside>Already signed up.</aside>";
            } else {
                $simple->createOrder($_POST['email']);
            }
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




