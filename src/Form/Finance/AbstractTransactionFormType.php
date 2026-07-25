<?php

declare(strict_types=1);

namespace App\Form\Finance;

use App\Dto\Finance\TransactionInputDto;
use App\Entity\Space;
use App\Enum\TransactionTypeEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

abstract class AbstractTransactionFormType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TransactionInputDto::class,
            'constraints' => [
                new Assert\Callback([$this, 'validateTransferDestination']),
            ],
        ]);
        $resolver->setRequired('space');
        $resolver->setAllowedTypes('space', Space::class);
    }

    public function validateTransferDestination(TransactionInputDto $input, ExecutionContextInterface $context): void
    {
        if ($input->type !== TransactionTypeEnum::TRANSFER) {
            return;
        }

        if ($input->destinationAccount === null) {
            $context->buildViolation('Le compte destinataire est obligatoire pour un virement.')
                ->atPath('destinationAccount')
                ->addViolation();

            return;
        }

        if ($input->destinationAccount->getId() === $input->account->getId()) {
            $context->buildViolation('Le compte destinataire doit être différent du compte source.')
                ->atPath('destinationAccount')
                ->addViolation();
        }
    }
}
