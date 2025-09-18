<?php

namespace App\Validator;

use App\Service\DomainRoutingService;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class DomainNotExcludedValidator extends ConstraintValidator
{
    private DomainRoutingService $domainRoutingService;

    public function __construct(DomainRoutingService $domainRoutingService)
    {
        $this->domainRoutingService = $domainRoutingService;
    }

    public function validate($value, Constraint $constraint): void
    {
        if (!$constraint instanceof DomainNotExcluded) {
            throw new UnexpectedTypeException($constraint, DomainNotExcluded::class);
        }

        // null and empty values are valid
        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        if (!$this->domainRoutingService->isDomainAvailable($value)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ value }}', $value)
                ->addViolation();
        }
    }
}