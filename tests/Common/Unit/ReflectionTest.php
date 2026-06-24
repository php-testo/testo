<?php

declare(strict_types=1);

namespace Tests\Common\Unit;

use Testo\Assert;
use Testo\Codecov\Covers;
use Testo\Common\Reflection;
use Testo\Test;
use Tests\Common\Stub\AnotherMarkerAttribute;
use Tests\Common\Stub\AttributedChild;
use Tests\Common\Stub\BaseClass;
use Tests\Common\Stub\ChildClass;
use Tests\Common\Stub\ClassWithoutMarkedMethods;
use Tests\Common\Stub\ClassWithTrait;
use Tests\Common\Stub\InnerTrait;
use Tests\Common\Stub\MarkerAttribute;
use Tests\Common\Stub\MergePolicyChild;
use Tests\Common\Stub\ReflectionCallStackHelper;
use Tests\Common\Stub\ReflectionClassAttribute;
use Tests\Common\Stub\TraitUsingTrait;

#[Test]
#[Covers(Reflection::class)]
final class ReflectionTest
{
    // --- fetchFunctionAttributes: MERGE_LAST ---

    public function mergeLast_storesAndReturnsLastNonEmptyLayer(): void
    {
        // MergePolicyChild::methodWithAttrOnBothLayers has AnotherMarkerAttribute on child,
        // MarkerAttribute on parent. MERGE_LAST returns the parent (last) layer.
        $ref = new \ReflectionMethod(MergePolicyChild::class, 'methodWithAttrOnBothLayers');

        $attrs = Reflection::fetchFunctionAttributes($ref, mergePolicy: Reflection::MERGE_LAST);

        Assert::count($attrs, 1);
        Assert::same($attrs[0]->getName(), MarkerAttribute::class);
    }

    public function mergeLast_returnsEmptyWhenNoLayersHaveAttributes(): void
    {
        $ref = new \ReflectionMethod(MergePolicyChild::class, 'methodWithNoAttr');

        $attrs = Reflection::fetchFunctionAttributes($ref, mergePolicy: Reflection::MERGE_LAST);

        Assert::same($attrs, []);
    }

    public function mergeAll_accumulatesAttributesAcrossLayers(): void
    {
        // Child has AnotherMarkerAttribute, parent has MarkerAttribute → both returned.
        $ref = new \ReflectionMethod(MergePolicyChild::class, 'methodWithAttrOnBothLayers');

        $attrs = Reflection::fetchFunctionAttributes($ref, mergePolicy: Reflection::MERGE_ALL);

        Assert::count($attrs, 2);
    }

    public function mergeAll_earlyCutsAtLimitAfterAccumulation(): void
    {
        // Two attributes across layers; limit=1 must stop after the first layer fills the cap.
        $ref = new \ReflectionMethod(MergePolicyChild::class, 'methodWithAttrOnBothLayers');

        $attrs = Reflection::fetchFunctionAttributes($ref, limit: 1, mergePolicy: Reflection::MERGE_ALL);

        Assert::count($attrs, 1);
    }

    public function mergeFirst_returnsFirstNonEmptyLayerWithinLimit(): void
    {
        // methodWithAttrOnChildOnly has 1 attr and limit=1, so the result fits the limit unsliced.
        $ref = new \ReflectionMethod(MergePolicyChild::class, 'methodWithAttrOnChildOnly');

        $attrs = Reflection::fetchFunctionAttributes($ref, limit: 1, mergePolicy: Reflection::MERGE_FIRST);

        Assert::count($attrs, 1);
        Assert::same($attrs[0]->getName(), MarkerAttribute::class);
    }

    public function mergeFirst_capsFirstLayerWhenItExceedsLimit(): void
    {
        // methodWithTwoOwnAttributes carries two attributes in its first layer; limit=1
        // forces the MERGE_FIRST array_slice branch.
        $ref = new \ReflectionMethod(MergePolicyChild::class, 'methodWithTwoOwnAttributes');

        $attrs = Reflection::fetchFunctionAttributes(
            $ref,
            attributeClass: ReflectionClassAttribute::class,
            limit: 1,
            mergePolicy: Reflection::MERGE_FIRST,
        );

        Assert::count($attrs, 1);
    }

    public function mergeLast_returnsLastLayerWithinLimit(): void
    {
        // The last non-empty layer holds a single attribute, so limit=1 returns it unsliced.
        $ref = new \ReflectionMethod(MergePolicyChild::class, 'methodWithAttrOnBothLayers');

        $attrs = Reflection::fetchFunctionAttributes($ref, limit: 1, mergePolicy: Reflection::MERGE_LAST);

        Assert::count($attrs, 1);
    }

    public function mergeLast_capsLastLayerWhenItExceedsLimit(): void
    {
        // methodWithTwoOwnAttributes has no prototype, so its single (two-attribute) layer
        // becomes the last non-empty layer; limit=1 forces the MERGE_LAST array_slice branch.
        $ref = new \ReflectionMethod(MergePolicyChild::class, 'methodWithTwoOwnAttributes');

        $attrs = Reflection::fetchFunctionAttributes(
            $ref,
            attributeClass: ReflectionClassAttribute::class,
            limit: 1,
            mergePolicy: Reflection::MERGE_LAST,
        );

        Assert::count($attrs, 1);
    }

    // --- fetchClassAttributes: traits and merge policies ---

    public function fetchClassAttributes_mergeFirst_capsFirstLayerWhenExceedsLimit(): void
    {
        // ClassWithTrait with includeTraits=true has 3 attributes in the first (and only) layer:
        // 'ownClass' (class), 'outerTrait' (trait), 'innerTrait' (trait-of-trait).
        // MERGE_FIRST + limit=1 forces the array_slice branch.
        $attrs = Reflection::fetchClassAttributes(
            ClassWithTrait::class,
            includeParents: false,
            includeTraits: true,
            attributeClass: ReflectionClassAttribute::class,
            limit: 1,
            mergePolicy: Reflection::MERGE_FIRST,
        );

        Assert::count($attrs, 1);
    }

    public function fetchClassAttributes_includesTraitAttributes(): void
    {
        // ClassWithTrait uses TraitUsingTrait which carries ReflectionClassAttribute('outerTrait').
        $attrs = Reflection::fetchClassAttributes(
            ClassWithTrait::class,
            includeTraits: true,
            attributeClass: ReflectionClassAttribute::class,
        );

        $labels = \array_map(static fn(\ReflectionAttribute $a) => $a->newInstance()->label, $attrs);
        Assert::true(\in_array('outerTrait', $labels, true), 'outerTrait from trait expected');
    }

    public function fetchClassAttributes_mergeAll_accumulatesWithLimit(): void
    {
        // ClassWithTrait has own attr plus trait attrs. MERGE_ALL with limit=1.
        $attrs = Reflection::fetchClassAttributes(
            ClassWithTrait::class,
            includeParents: false,
            includeTraits: true,
            attributeClass: ReflectionClassAttribute::class,
            limit: 1,
            mergePolicy: Reflection::MERGE_ALL,
        );

        Assert::count($attrs, 1);
    }

    public function fetchClassAttributes_mergeLast_returnsMostDistantParentLayer(): void
    {
        // AttributedChild('childLayer') extends AttributedParent('parentLayer').
        // MERGE_LAST keeps overwriting with each parent layer, so the most distant
        // parent wins.
        $attrs = Reflection::fetchClassAttributes(
            AttributedChild::class,
            includeParents: true,
            includeTraits: false,
            attributeClass: ReflectionClassAttribute::class,
            mergePolicy: Reflection::MERGE_LAST,
        );

        Assert::count($attrs, 1);
        Assert::same($attrs[0]->newInstance()->label, 'parentLayer');
    }

    public function fetchClassAttributes_mergeLast_capsResultWhenLayerExceedsLimit(): void
    {
        // ClassWithTrait with includeTraits=true exposes 3 attributes in its single layer;
        // MERGE_LAST + limit=1 forces the array_slice branch.
        $attrs = Reflection::fetchClassAttributes(
            ClassWithTrait::class,
            includeParents: false,
            includeTraits: true,
            attributeClass: ReflectionClassAttribute::class,
            limit: 1,
            mergePolicy: Reflection::MERGE_LAST,
        );

        Assert::count($attrs, 1);
    }

    public function fetchClassAttributes_acceptsStringClassName(): void
    {
        // Passing a string triggers `is_string($class) and $class = new ReflectionClass($class)`.
        $attrs = Reflection::fetchClassAttributes(
            ClassWithTrait::class,
            includeParents: false,
            includeTraits: false,
            attributeClass: ReflectionClassAttribute::class,
        );

        Assert::count($attrs, 1);
    }

    // --- fetchTraits: traits-of-traits ---

    public function fetchTraits_includesTraitsUsedByTraits(): void
    {
        // TraitUsingTrait uses InnerTrait → fetchTraits should return both.
        $traits = Reflection::fetchTraits(ClassWithTrait::class);

        Assert::true(\in_array(TraitUsingTrait::class, $traits, true), 'TraitUsingTrait expected');
        Assert::true(\in_array(InnerTrait::class, $traits, true), 'InnerTrait (trait-of-trait) expected');
    }

    public function fetchTraits_withoutParents_returnsOnlyOwnTraits(): void
    {
        $traits = Reflection::fetchTraits(ClassWithTrait::class, includeParents: false);

        Assert::true(\in_array(TraitUsingTrait::class, $traits, true));
    }

    // --- findMethodsWithAttribute ---

    public function findMethodsWithAttribute_findsDirectlyAnnotatedMethods(): void
    {
        $methods = Reflection::findMethodsWithAttribute(BaseClass::class, MarkerAttribute::class);

        $names = \array_map(static fn(\ReflectionMethod $m) => $m->getName(), $methods);
        \sort($names);

        Assert::same($names, ['baseMethod', 'overriddenMethod']);
    }

    public function findMethodsWithAttribute_findsMethodsViaPrototype(): void
    {
        // ChildClass overrides interfaceMethod (no direct attr); prototype in interface has MarkerAttribute.
        $methods = Reflection::findMethodsWithAttribute(ChildClass::class, MarkerAttribute::class);

        $names = \array_map(static fn(\ReflectionMethod $m) => $m->getName(), $methods);

        Assert::true(\in_array('interfaceMethod', $names, true));
    }

    public function findMethodsWithAttribute_withoutPrototypes_skipsPrototypeSearch(): void
    {
        $methods = Reflection::findMethodsWithAttribute(
            ChildClass::class,
            MarkerAttribute::class,
            includePrototypes: false,
        );

        $names = \array_map(static fn(\ReflectionMethod $m) => $m->getName(), $methods);

        Assert::false(\in_array('interfaceMethod', $names, true));
        Assert::true(\in_array('baseMethod', $names, true));
    }

    public function findMethodsWithAttribute_returnsEmptyForUnmarkedClass(): void
    {
        $methods = Reflection::findMethodsWithAttribute(ClassWithoutMarkedMethods::class, MarkerAttribute::class);

        Assert::same($methods, []);
    }

    public function findMethodsWithAttribute_acceptsReflectionClassInstance(): void
    {
        $ref = new \ReflectionClass(BaseClass::class);

        $methods = Reflection::findMethodsWithAttribute($ref, MarkerAttribute::class);

        $names = \array_map(static fn(\ReflectionMethod $m) => $m->getName(), $methods);
        Assert::true(\in_array('baseMethod', $names, true));
    }

    public function findMethodsWithAttribute_filtersByAttributeClass(): void
    {
        $methods = Reflection::findMethodsWithAttribute(ChildClass::class, AnotherMarkerAttribute::class);

        $names = \array_map(static fn(\ReflectionMethod $m) => $m->getName(), $methods);

        Assert::same($names, ['middleMethod']);
    }

    // --- getAttributesFromCallStack ---

    public function getAttributesFromCallStack_findsAttributeOnCallerMethod(): void
    {
        $helper = new ReflectionCallStackHelper();

        // markedMethod calls getAttributesFromCallStack; it has #[ReflectionClassAttribute('markedMethod')]
        $attrs = $helper->markedMethod(ReflectionClassAttribute::class);

        $labels = \array_map(static fn(\ReflectionAttribute $a) => $a->newInstance()->label, $attrs);
        Assert::true(\in_array('markedMethod', $labels, true));
    }

    public function getAttributesFromCallStack_returnsEmptyForUnmarkedMethod(): void
    {
        $helper = new ReflectionCallStackHelper();

        // unmarkedMethod has no attribute
        $attrs = $helper->unmarkedMethod(ReflectionClassAttribute::class);

        Assert::same($attrs, []);
    }

    public function getAttributesFromCallStack_findsAttributesFromNestedCallers(): void
    {
        $helper = new ReflectionCallStackHelper();

        // callerMethod (#[ReflectionClassAttribute('callerMethod')]) calls markedMethod
        // (#[ReflectionClassAttribute('markedMethod')]) which calls getAttributesFromCallStack.
        $attrs = $helper->callerMethod(ReflectionClassAttribute::class);

        $labels = \array_map(static fn(\ReflectionAttribute $a) => $a->newInstance()->label, $attrs);
        Assert::true(\in_array('markedMethod', $labels, true));
        Assert::true(\in_array('callerMethod', $labels, true));
    }

    public function getAttributesFromCallStack_includesClassAttributesWhenRequested(): void
    {
        $helper = new ReflectionCallStackHelper();

        // With includeClasses=true, the class attribute 'helperClass' should appear too.
        $attrs = $helper->markedMethod(
            ReflectionClassAttribute::class,
            includePrototypes: true,
            includeClasses: true,
        );

        $labels = \array_map(static fn(\ReflectionAttribute $a) => $a->newInstance()->label, $attrs);
        Assert::true(\in_array('helperClass', $labels, true));
    }

    public function getAttributesFromCallStack_respectsLimitAndStopsEarly(): void
    {
        $helper = new ReflectionCallStackHelper();

        // callerMethod + markedMethod = 2 labeled attributes in stack; limit=1 must cap.
        $attrs = $helper->callerMethod(ReflectionClassAttribute::class, limit: 1);

        Assert::count($attrs, 1);
    }

    public function getAttributesFromCallStack_withNullAttributeClass_returnsAllAttributes(): void
    {
        $helper = new ReflectionCallStackHelper();

        // null attributeClass returns everything in the call stack
        $attrs = $helper->markedMethod(null);

        Assert::true(\count($attrs) >= 1);
    }
}
