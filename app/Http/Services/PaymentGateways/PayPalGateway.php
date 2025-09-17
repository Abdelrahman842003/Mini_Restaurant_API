<?php

namespace App\Http\Services\PaymentGateways;

use App\Http\Interfaces\PaymentGatewayInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PayPal\Api\Amount;
use PayPal\Api\Item;
use PayPal\Api\ItemList;
use PayPal\Api\Payer;
use PayPal\Api\Payment;
use PayPal\Api\PaymentExecution;
use PayPal\Api\RedirectUrls;
use PayPal\Api\Transaction;
use PayPal\Auth\OAuthTokenCredential;
use PayPal\Rest\ApiContext;
use Exception;

class PayPalGateway implements PaymentGatewayInterface
{
    private $apiContext;

    public function __construct()
    {
        $this->apiContext = new ApiContext(
            new OAuthTokenCredential(
                config('services.paypal.client_id'),
                config('services.paypal.client_secret')
            )
        );

        $this->apiContext->setConfig([
            'mode' => config('services.paypal.mode', 'sandbox'),
            'log.LogEnabled' => config('services.paypal.log.enabled', true),
            'log.FileName' => storage_path('logs/paypal.log'),
            'log.LogLevel' => config('services.paypal.log.level', 'ERROR')
        ]);
    }

    /**
     * Create a payment with PayPal
     */
    public function createPayment(array $data): array
    {
        try {
            $this->validatePaymentData($data);

            // Create payer
            $payer = new Payer();
            $payer->setPaymentMethod('paypal');

            // Create item
            $item = new Item();
            $item->setName($data['description'] ?? 'Restaurant Order Payment')
                 ->setCurrency($data['currency'] ?? 'USD')
                 ->setQuantity(1)
                 ->setPrice($data['amount']);

            $itemList = new ItemList();
            $itemList->setItems([$item]);

            // Create amount
            $amount = new Amount();
            $amount->setCurrency($data['currency'] ?? 'USD')
                   ->setTotal($data['amount']);

            // Create transaction
            $transaction = new Transaction();
            $transaction->setAmount($amount)
                       ->setItemList($itemList)
                       ->setDescription($data['description'] ?? 'Restaurant Payment')
                       ->setInvoiceNumber($data['invoice_number'] ?? uniqid());

            // Set redirect URLs
            $redirectUrls = new RedirectUrls();
            $redirectUrls->setReturnUrl($data['return_url'] ?? config('services.paypal.return_url'))
                        ->setCancelUrl($data['cancel_url'] ?? config('services.paypal.cancel_url'));

            // Create payment
            $payment = new Payment();
            $payment->setIntent('sale')
                   ->setPayer($payer)
                   ->setRedirectUrls($redirectUrls)
                   ->setTransactions([$transaction]);

            $payment->create($this->apiContext);

            // Get approval URL
            $approvalUrl = null;
            foreach ($payment->getLinks() as $link) {
                if ($link->getRel() === 'approval_url') {
                    $approvalUrl = $link->getHref();
                    break;
                }
            }

            return [
                'success' => true,
                'transaction_id' => $payment->getId(),
                'approval_url' => $approvalUrl,
                'payment_method' => 'paypal',
                'amount' => $data['amount'],
                'status' => 'created',
                'redirect_required' => true,
                'payment_object' => $payment
            ];

        } catch (Exception $e) {
            Log::error('PayPal payment creation failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment_method' => 'paypal'
            ];
        }
    }

    /**
     * Handle PayPal callback
     */
    public function handleCallback(Request $request): array
    {
        try {
            $paymentId = $request->query('paymentId');
            $payerId = $request->query('PayerID');
            $token = $request->query('token');

            if (!$paymentId || !$payerId) {
                return [
                    'success' => false,
                    'error' => 'Missing payment parameters',
                    'payment_method' => 'paypal'
                ];
            }

            // Execute payment
            $result = $this->executePayment($paymentId, $payerId);

            if ($result['success']) {
                return [
                    'success' => true,
                    'status' => 'completed',
                    'transaction_id' => $paymentId,
                    'payer_id' => $payerId,
                    'payment_method' => 'paypal',
                    'execution_result' => $result
                ];
            }

            return [
                'success' => false,
                'status' => 'failed',
                'error' => $result['error'] ?? 'Payment execution failed',
                'payment_method' => 'paypal'
            ];

        } catch (Exception $e) {
            Log::error('PayPal callback error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment_method' => 'paypal'
            ];
        }
    }

    /**
     * Verify payment status directly from PayPal API
     */
    public function verifyPayment(string $transactionId): array
    {
        try {
            $payment = Payment::get($transactionId, $this->apiContext);

            $state = $payment->getState();
            $transactions = $payment->getTransactions();
            $amount = 0;

            if (!empty($transactions)) {
                $amount = $transactions[0]->getAmount()->getTotal();
            }

            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'status' => $state,
                'amount' => $amount,
                'payment_method' => 'paypal',
                'verified' => true,
                'payment_details' => [
                    'state' => $state,
                    'create_time' => $payment->getCreateTime(),
                    'update_time' => $payment->getUpdateTime()
                ]
            ];

        } catch (Exception $e) {
            Log::error('PayPal payment verification failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment_method' => 'paypal',
                'verified' => false
            ];
        }
    }

    /**
     * Validate payment data before processing
     */
    public function validatePaymentData(array $paymentData): bool
    {
        if (!isset($paymentData['amount']) || $paymentData['amount'] <= 0) {
            throw new Exception('Invalid amount provided');
        }

        if (isset($paymentData['currency']) && !in_array(strtoupper($paymentData['currency']), ['USD', 'EUR', 'GBP'])) {
            throw new Exception('Unsupported currency for PayPal');
        }

        return true;
    }

    /**
     * Get gateway name
     */
    public function getGatewayName(): string
    {
        return 'paypal';
    }

    /**
     * Execute PayPal payment after user approval
     */
    public function executePayment(string $paymentId, string $payerId): array
    {
        try {
            $payment = Payment::get($paymentId, $this->apiContext);

            $execution = new PaymentExecution();
            $execution->setPayerId($payerId);

            $result = $payment->execute($execution, $this->apiContext);

            return [
                'success' => true,
                'state' => $result->getState(),
                'payment_id' => $result->getId(),
                'payer_id' => $payerId,
                'transactions' => $result->getTransactions()
            ];

        } catch (Exception $e) {
            Log::error('PayPal payment execution failed: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancel PayPal payment
     */
    public function cancelPayment(string $paymentId): array
    {
        try {
            // PayPal doesn't require explicit cancellation
            // The payment is automatically cancelled if not executed

            Log::info('PayPal payment cancelled', ['payment_id' => $paymentId]);

            return [
                'success' => true,
                'status' => 'cancelled',
                'transaction_id' => $paymentId,
                'payment_method' => 'paypal'
            ];

        } catch (Exception $e) {
            Log::error('PayPal payment cancellation error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'payment_method' => 'paypal'
            ];
        }
    }
}
