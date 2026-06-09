<?php

namespace App\Module\Org;

use App\Repository\Org\AccountRepository;

class AccountModule
{
    private AccountRepository $repository;

    public function __construct(AccountRepository $repository)
    {
        $this->repository = $repository;
    }

    public function index()
    {
        $accounts = $this->repository->getAccounts();

        require __DIR__ .
            '/../../../templates/pages/org/accounts.php';
    }

    public function edit($id)
    {
        $account =
            $this->repository->getAccount((int)$id);

        require __DIR__ .
            '/../../../templates/pages/org/account-edit.php';
    }

    public function update($id)
    {
        $data = [
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'role' => $_POST['role'],
            'active' => isset($_POST['active']) ? 1 : 0
        ];

        $this->repository
            ->updateAccount((int)$id, $data);

        header('Location: /org/accounts');
        exit;
    }
}