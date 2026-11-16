<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Company;
use App\Models\ContactPerson;
use App\Models\License;

class CompanyController extends Controller
{
    private const UFS = [
        'AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB',
        'PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO',
    ];

    public function profile(): void
    {
        $userId = (int) Auth::id();

        $this->render('company/profile', [
            'company'       => Company::findByUserId($userId) ?? [],
            'contacts'      => ContactPerson::getByUserId($userId),
            'activeLicense' => License::getActiveByUserId($userId),
            'allLicenses'   => License::getAllByUserId($userId),
            'user'          => Auth::user(),
        ]);
    }

    public function updateProfile(): void
    {
        $this->validateCsrf();
        $userId = (int) Auth::id();

        $companyName = $this->text('company_name', '', 255);
        $tradingName = $this->text('trading_name', '', 255);

        if ($companyName === '' && $tradingName === '') {
            $this->redirect('empresa', ['error' => 'Informe ao menos a razão social ou o nome fantasia da empresa.']);
        }

        $cnpj = preg_replace('/[^0-9]/', '', $this->text('cnpj', '', 30)) ?? '';
        if ($cnpj !== '' && strlen($cnpj) !== 14 && strlen($cnpj) !== 11) {
            $this->redirect('empresa', ['error' => 'CNPJ/CPF inválido. Informe 14 dígitos (CNPJ) ou 11 (CPF).']);
        }

        $state = strtoupper($this->text('state', '', 2));
        if ($state !== '' && !in_array($state, self::UFS, true)) {
            $this->redirect('empresa', ['error' => 'UF inválida. Utilize a sigla do estado, por exemplo SP.']);
        }

        $contactEmail = $this->text('contact_email', '', 255);
        if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            $this->redirect('empresa', ['error' => 'O e-mail de contato informado não é válido.']);
        }

        $zip = preg_replace('/[^0-9]/', '', $this->text('zip_code', '', 20)) ?? '';
        if ($zip !== '' && strlen($zip) !== 8) {
            $this->redirect('empresa', ['error' => 'CEP inválido. Informe os 8 dígitos.']);
        }

        $whatsapp = preg_replace('/[^0-9]/', '', $this->text('whatsapp', '', 30)) ?? '';
        if ($whatsapp !== '' && (strlen($whatsapp) < 10 || strlen($whatsapp) > 13)) {
            $this->redirect('empresa', ['error' => 'WhatsApp inválido. Informe DDD + número, por exemplo 11995270932.']);
        }

        $data = [
            'company_name'  => $companyName,
            'trading_name'  => $tradingName !== '' ? $tradingName : $companyName,
            'cnpj'          => $cnpj !== '' ? $this->formatCnpj($cnpj) : null,
            'zip_code'      => $zip !== '' ? $zip : null,
            'address'       => $this->text('address', '', 255),
            'number'        => $this->text('number', '', 50),
            'district'      => $this->text('district', '', 100),
            'city'          => $this->text('city', '', 100),
            'state'         => $state,
            'phone'         => $this->text('phone', '', 50),
            'cell_phone'    => $this->text('cell_phone', '', 50),
            'whatsapp'      => $whatsapp,
            'contact_email' => $contactEmail,
            // URLs passam pelo filtro de esquema: nada de javascript: no perfil
            'website'       => safe_url($this->text('website', '', 255)),
            'facebook'      => safe_url($this->text('facebook', '', 255)),
            'instagram'     => safe_url($this->text('instagram', '', 255)),
            'linkedin'      => safe_url($this->text('linkedin', '', 255)),
            'youtube'       => safe_url($this->text('youtube', '', 255)),
            'twitter'       => safe_url($this->text('twitter', '', 255)),
            'skype'         => $this->text('skype', '', 100),
        ];

        Company::saveOrUpdate($userId, $data);
        Auth::flushCache();

        $this->redirect('empresa', ['success' => 'Dados da empresa atualizados com sucesso!']);
    }

    public function addContact(): void
    {
        $this->validateCsrf();
        $userId = (int) Auth::id();

        $name = $this->text('name', '', 150);
        $email = mb_strtolower($this->text('email', '', 255));

        if ($name === '' || $email === '') {
            $this->redirect('empresa', ['error' => 'Nome e e-mail do colaborador são obrigatórios.']);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->redirect('empresa', ['error' => 'Informe um e-mail válido para o colaborador.']);
        }

        if (ContactPerson::existsByEmail($userId, $email)) {
            $this->redirect('empresa', ['error' => 'Este colaborador já está cadastrado.']);
        }

        ContactPerson::create([
            'user_id'            => $userId,
            'name'               => $name,
            'occupation'         => $this->text('occupation', '', 100),
            'email'              => $email,
            'phone'              => $this->text('phone', '', 50),
            'include_in_license' => 0,
        ]);

        $this->redirect('empresa', ['success' => 'Colaborador cadastrado com sucesso!']);
    }

    public function deleteContact(): void
    {
        $this->validateCsrf();
        $userId = (int) Auth::id();
        $contactId = (int) $this->input('contact_id', 0);

        if (ContactPerson::delete($contactId, $userId) === 0) {
            $this->redirect('empresa', ['error' => 'Colaborador não encontrado.']);
        }

        $this->redirect('empresa', ['success' => 'Colaborador removido com sucesso.']);
    }

    public function toggleLicense(): void
    {
        $this->validateCsrf();
        $userId = (int) Auth::id();
        $contactId = (int) $this->input('contact_id', 0);
        $include = (int) $this->input('include', 0) === 1 ? 1 : 0;

        if ($include === 1) {
            $activeLicense = License::getActiveByUserId($userId);

            if (!$activeLicense) {
                $this->redirect('empresa', ['error' => 'Sua empresa não possui uma licença ativa para atribuir vagas.']);
            }

            $maxAllowed = max(1, (int) $activeLicense['max_users']);
            $currentIncluded = ContactPerson::countIncludedInLicense($userId);

            // O titular da conta ocupa uma das vagas do plano
            $occupiedAfterChange = $currentIncluded + 1 + 1;

            if ($occupiedAfterChange > $maxAllowed) {
                $this->redirect('empresa', [
                    'error' => "Limite de vagas do seu plano atingido ({$maxAllowed} usuários, incluindo você). "
                        . 'Contrate um plano maior para adicionar mais colaboradores.',
                ]);
            }
        }

        if (ContactPerson::toggleLicense($contactId, $userId, $include) === 0) {
            $this->redirect('empresa', ['error' => 'Colaborador não encontrado.']);
        }

        $this->redirect('empresa', [
            'success' => $include === 1
                ? 'Colaborador incluído na licença com sucesso!'
                : 'Colaborador removido da licença.',
        ]);
    }

    private function formatCnpj(string $digits): string
    {
        if (strlen($digits) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digits) ?? $digits;
        }

        if (strlen($digits) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digits) ?? $digits;
        }

        return $digits;
    }
}
