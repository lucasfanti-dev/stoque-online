<?php

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Models\Product;
use App\Models\User;

class LicenseService
{
    /**
     * Cria a licença correspondente a um pagamento — uma única vez.
     *
     * O PicPay envia o callback a cada mudança de status ("paid" e depois
     * "completed"), e a administração também pode liberar manualmente. Para
     * que nenhum caminho gere licença duplicada, a linha do pagamento é
     * travada e só a primeira execução encontra license_id nulo.
     *
     * @return array{success:bool, message:string, license_id:?int}
     */
    public static function activateFromPayment(int $paymentId, ?string $origemAcao = null): array
    {
        Database::beginTransaction();

        try {
            $payment = Database::fetch(
                "SELECT * FROM payments WHERE id = :id FOR UPDATE",
                ['id' => $paymentId]
            );

            if (!$payment) {
                Database::rollBack();
                return ['success' => false, 'message' => 'Pagamento não encontrado.', 'license_id' => null];
            }

            if (!empty($payment['license_id'])) {
                Database::commit();
                return [
                    'success'    => true,
                    'message'    => 'Este pagamento já havia liberado a licença #' . $payment['license_id'] . '.',
                    'license_id' => (int) $payment['license_id'],
                ];
            }

            $plan = Product::findById((int) $payment['plan_id'], true);

            if (!$plan) {
                Database::rollBack();
                error_log('[StoqueOnline] Pagamento ' . $paymentId . ' sem plano correspondente.');
                return ['success' => false, 'message' => 'O plano deste pagamento não existe mais.', 'license_id' => null];
            }

            $startDate = date('Y-m-d');
            $endDate = date('Y-m-d', strtotime('+' . (int) $plan['duration_days'] . ' days'));

            $licenseId = Database::insert('user_licenses', [
                'user_id'    => (int) $payment['user_id'],
                'plan_id'    => (int) $plan['id'],
                'price'      => $payment['amount'],
                'max_users'  => (int) $plan['max_users'],
                'start_date' => $startDate,
                'end_date'   => $endDate,
                'status'     => 1,
                'is_closed'  => 0,
                'payment_id' => (int) $payment['id'],
                'origin'     => 'payment',
            ]);

            Database::update('payments', [
                'license_id' => $licenseId,
                'paid_at'    => $payment['paid_at'] ?: date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => (int) $payment['id']]);

            Database::update(
                'users',
                ['first_access_free' => 0],
                'id = :id',
                ['id' => (int) $payment['user_id']]
            );

            Database::commit();

            Auth::logAudit(
                (int) $payment['user_id'],
                null,
                $origemAcao ?? 'license_activated_by_payment'
            );

            $buyer = User::findById((int) $payment['user_id']);

            if ($buyer) {
                MailService::sendPaymentConfirmation(
                    (string) $buyer['name'],
                    (string) $buyer['email'],
                    (string) $plan['name'],
                    $endDate
                );
            }

            return [
                'success'    => true,
                'message'    => 'Licença do plano ' . $plan['name'] . ' liberada até '
                    . date('d/m/Y', strtotime($endDate)) . '.',
                'license_id' => $licenseId,
            ];
        } catch (\Throwable $e) {
            Database::rollBack();
            error_log('[StoqueOnline] Falha ao ativar licença do pagamento ' . $paymentId . ': ' . $e->getMessage());

            return ['success' => false, 'message' => 'Erro ao liberar a licença: ' . $e->getMessage(), 'license_id' => null];
        }
    }
}
