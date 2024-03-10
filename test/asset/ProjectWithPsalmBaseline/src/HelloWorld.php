<?php

namespace Acme\ProjectWithPsalmBaseline;

/**
 * @psalm-api
 */
class HelloWorld
{
    /**
     * This function has full test coverage, but the test is effectively assertion-free and useless. But
     * because the function also has Psalm errors that are in the baseline, ISAP will think the test is good
     * and give us a 100% MSI.
     */
    public function takesImplictMixedReturnsIntAndIsCoveredByBaseline($mixture): int
    {
        return $mixture + 2;
    }
}