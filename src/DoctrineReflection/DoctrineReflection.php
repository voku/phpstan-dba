<?php

declare(strict_types=1);

namespace staabm\PHPStanDba\DoctrineReflection;

use PHPStan\Reflection\MethodReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\Constant\ConstantArrayType;
use PHPStan\Type\Constant\ConstantArrayTypeBuilder;
use PHPStan\Type\Constant\ConstantBooleanType;
use PHPStan\Type\Constant\ConstantIntegerType;
use PHPStan\Type\Constant\ConstantStringType;
use PHPStan\Type\Generic\GenericObjectType;
use PHPStan\Type\IntegerRangeType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\Type;
use PHPStan\Type\TypeCombinator;
use staabm\PHPStanDba\QueryReflection\QueryReflector;
use Traversable;

final class DoctrineReflection
{
    public function fetchResultType(MethodReflection $methodReflection, Type $resultRowType): ?Type
    {
        $usedMethod = strtolower($methodReflection->getName());

        switch ($usedMethod) {
            case 'fetchallkeyvalue':
            case 'iteratekeyvalue':
                $fetchType = QueryReflector::FETCH_TYPE_KEY_VALUE;
                break;
            case 'fetchone':
                $fetchType = QueryReflector::FETCH_TYPE_ONE;
                break;
            case 'fetchfirstcolumn':
            case 'iteratecolumn':
                $fetchType = QueryReflector::FETCH_TYPE_FIRST_COL;
                break;
            case 'fetchnumeric':
            case 'fetchallnumeric':
            case 'iteratenumeric':
                $fetchType = QueryReflector::FETCH_TYPE_NUMERIC;
                break;
            case 'fetchassociative':
            case 'fetchallassociative':
            case 'iterateassociative':
                $fetchType = QueryReflector::FETCH_TYPE_ASSOC;
                break;
            default:
                $fetchType = QueryReflector::FETCH_TYPE_BOTH;
        }

        if (QueryReflector::FETCH_TYPE_BOTH !== $fetchType && $resultRowType instanceof ConstantArrayType) {
            $builder = ConstantArrayTypeBuilder::createEmpty();

            $keyTypes = $resultRowType->getKeyTypes();
            $valueTypes = $resultRowType->getValueTypes();

            if (QueryReflector::FETCH_TYPE_KEY_VALUE === $fetchType) {
                // $valueType contain 'BOTH' fetched values
                if (\count($valueTypes) < 4) {
                    return null;
                }

                if (\in_array($usedMethod, ['fetchallkeyvalue'], true)) {
                    return new ArrayType($valueTypes[0], $valueTypes[2]);
                }

                return new GenericObjectType(Traversable::class, [$valueTypes[0], $valueTypes[2]]);
            }

            foreach ($keyTypes as $i => $keyType) {
                if (QueryReflector::FETCH_TYPE_ONE === $fetchType) {
                    return $valueTypes[$i];
                }
                if (QueryReflector::FETCH_TYPE_FIRST_COL === $fetchType) {
                    if (\in_array($usedMethod, ['iteratecolumn'], true)) {
                        return new GenericObjectType(Traversable::class, [new IntegerType(), $valueTypes[$i]]);
                    }

                    return new ArrayType(IntegerRangeType::fromInterval(0, null), $valueTypes[$i]);
                }

                if (QueryReflector::FETCH_TYPE_NUMERIC === $fetchType && $keyType instanceof ConstantIntegerType) {
                    $builder->setOffsetValueType($keyType, $valueTypes[$i]);
                } elseif (QueryReflector::FETCH_TYPE_ASSOC === $fetchType && $keyType instanceof ConstantStringType) {
                    $builder->setOffsetValueType($keyType, $valueTypes[$i]);
                }
            }

            $resultType = $builder->getArray();

            if (\in_array($usedMethod, ['iterateassociative', 'iteratenumeric'], true)) {
                return new GenericObjectType(Traversable::class, [new IntegerType(), $resultType]);
            }

            if (\in_array($usedMethod, ['fetchallnumeric', 'fetchallassociative'], true)) {
                return new ArrayType(IntegerRangeType::fromInterval(0, null), $resultType);
            }

            // false is returned if no rows are found.
            $resultType = TypeCombinator::union($resultType, new ConstantBooleanType(false));

            return $resultType;
        }

        return null;
    }
}
