<?php

namespace App\Http;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/** Corps de requete rejete (type incorrect ou contraintes non respectees) : reponse 422. */
final class DtoInvalideException extends HttpException
{
    /** @var list<array{propertyPath: string, title: string}> */
    private array $violations;

    /** @param list<array{propertyPath: string, title: string}> $violations */
    public function __construct(array $violations)
    {
        parent::__construct(422, 'Données invalides.');
        $this->violations = $violations;
    }

    public static function depuis(ConstraintViolationListInterface $liste): self
    {
        $violations = [];
        foreach ($liste as $violation) {
            $violations[] = ['propertyPath' => $violation->getPropertyPath(), 'title' => (string) $violation->getMessage()];
        }

        return new self($violations);
    }

    /** @return list<array{propertyPath: string, title: string}> */
    public function getViolations(): array
    {
        return $this->violations;
    }
}
