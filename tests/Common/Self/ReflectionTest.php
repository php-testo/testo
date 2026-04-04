<?php

declare(strict_types=1);

namespace Tests\Common\Self;

use Testo\Assert;
use Testo\Common\Reflection;
use Tests\Common\Stub\AnotherMarkerAttribute;
use Tests\Common\Stub\BaseClass;
use Tests\Common\Stub\CallStack\CallStackAttribute;
use Tests\Common\Stub\CallStack\CallStackBaseClass;
use Tests\Common\Stub\CallStack\CallStackChildClass;
use Tests\Common\Stub\CallStack\CallStackTestClass;
use Tests\Common\Stub\ChildClass;
use Tests\Common\Stub\ClassWithoutMarkedMethods;
use Tests\Common\Stub\MarkerAttribute;
use Tests\Common\Stub\MergePolicyChild;
use Tests\Common\Stub\MiddleClass;

use function Tests\Common\Stub\CallStack\nestedFunction;
use function Tests\Common\Stub\CallStack\topLevelFunction;
use function Tests\Common\Stub\CallStack\unmarkedFunction;

require_once __DIR__ . '/../Stub/CallStack/CallStackHelpers.php';

function testFindsDirectlyMarkedMethods(): void
{
    $methods = Reflection::findMethodsWithAttribute(BaseClass::class, MarkerAttribute::class);

    $names = \array_map(static fn(\ReflectionMethod $m) => $m->getName(), $methods);
    \sort($names);

    Assert::same($names, ['baseMethod', 'overriddenMethod']);
}

function testFindsInheritedMarkedMethods(): void
{
    $methods = Reflection::findMethodsWithAttribute(MiddleClass::class, MarkerAttribute::class);

    $names = \array_map(static fn(\ReflectionMethod $m) => $m->getName(), $methods);
    \sort($names);

    Assert::same($names, ['baseMethod', 'overriddenMethod']);
}

function testFindsMethodsMarkedInPrototype(): void
{
    $methods = Reflection::findMethodsWithAttribute(ChildClass::class, MarkerAttribute::class);

    $names = \array_map(static fn(\ReflectionMethod $m) => $m->getName(), $methods);
    \sort($names);

    Assert::same($names, ['baseMethod', 'childMethod', 'interfaceMethod', 'overriddenMethod']);
}

function testFindsMethodsFromInterface(): void
{
    $methods = Reflection::findMethodsWithAttribute(ChildClass::class, MarkerAttribute::class);

    $names = \array_map(static fn(\ReflectionMethod $m) => $m->getName(), $methods);

    Assert::true(\in_array('interfaceMethod', $names, true));
}

function testWithoutPrototypesSkipsPrototypeSearch(): void
{
    $methods = Reflection::findMethodsWithAttribute(
        ChildClass::class,
        MarkerAttribute::class,
        includePrototypes: false,
    );

    $names = \array_map(static fn(\ReflectionMethod $m) => $m->getName(), $methods);
    \sort($names);

    // baseMethod has attribute directly, childMethod has attribute directly
    // interfaceMethod and overriddenMethod only have attributes in prototypes
    Assert::same($names, ['baseMethod', 'childMethod']);
}

function testReturnsEmptyArrayWhenNoMatches(): void
{
    $methods = Reflection::findMethodsWithAttribute(ClassWithoutMarkedMethods::class, MarkerAttribute::class);

    Assert::same($methods, []);
}

function testFiltersByAttributeClass(): void
{
    $methods = Reflection::findMethodsWithAttribute(ChildClass::class, AnotherMarkerAttribute::class);

    $names = \array_map(static fn(\ReflectionMethod $m) => $m->getName(), $methods);

    Assert::same($names, ['middleMethod']);
}

function testAcceptsReflectionClassInstance(): void
{
    $ref = new \ReflectionClass(BaseClass::class);
    $methods = Reflection::findMethodsWithAttribute($ref, MarkerAttribute::class);

    $names = \array_map(static fn(\ReflectionMethod $m) => $m->getName(), $methods);
    \sort($names);

    Assert::same($names, ['baseMethod', 'overriddenMethod']);
}

function testGetAttributesFromCallStackWithFunction(): void
{
    $attributes = topLevelFunction(CallStackAttribute::class);

    Assert::count($attributes, 1);
    Assert::same($attributes[0]->getName(), CallStackAttribute::class);
    $instance = $attributes[0]->newInstance();
    Assert::same($instance->label, 'topFunction');
}

function testGetAttributesFromCallStackWithNestedFunctions(): void
{
    $attributes = nestedFunction(CallStackAttribute::class);

    Assert::count($attributes, 2);

    $labels = \array_map(
        static fn(\ReflectionAttribute $attr) => $attr->newInstance()->label,
        $attributes,
    );

    Assert::true(\in_array('topFunction', $labels, true));
    Assert::true(\in_array('nestedFunction', $labels, true));
}

function testGetAttributesFromCallStackWithMethod(): void
{
    $obj = new CallStackTestClass();
    $attributes = $obj->methodA(CallStackAttribute::class);

    Assert::count($attributes, 1);
    Assert::same($attributes[0]->newInstance()->label, 'methodA');
}

function testGetAttributesFromCallStackWithNestedMethods(): void
{
    $obj = new CallStackTestClass();
    $attributes = $obj->methodB(CallStackAttribute::class);

    Assert::count($attributes, 2);

    $labels = \array_map(
        static fn(\ReflectionAttribute $attr) => $attr->newInstance()->label,
        $attributes,
    );

    Assert::true(\in_array('methodA', $labels, true));
    Assert::true(\in_array('methodB', $labels, true));
}

function testGetAttributesFromCallStackWithInheritance(): void
{
    $obj = new CallStackChildClass();
    $attributes = $obj->baseMethod(CallStackAttribute::class);

    Assert::count($attributes, 1);
    Assert::same($attributes[0]->newInstance()->label, 'baseMethod');
}

function testGetAttributesFromCallStackFindsPrototypeAttributes(): void
{
    $obj = new CallStackChildClass();
    $attributes = $obj->childMethod(CallStackAttribute::class);

    $labels = \array_map(
        static fn(\ReflectionAttribute $attr) => $attr->newInstance()->label,
        $attributes,
    );

    // Should find: childMethod (direct), overridden (from prototype in base class)
    Assert::true(\in_array('childMethod', $labels, true));
    Assert::true(\in_array('overridden', $labels, true));
}

function testGetAttributesFromCallStackWithoutPrototypes(): void
{
    $obj = new CallStackChildClass();
    $attributes = $obj->childMethod(CallStackAttribute::class, false);

    $labels = \array_map(
        static fn(\ReflectionAttribute $attr) => $attr->newInstance()->label,
        $attributes,
    );

    // Should find only childMethod (direct attribute), not overridden (from prototype)
    Assert::true(\in_array('childMethod', $labels, true));
    Assert::false(\in_array('overridden', $labels, true));
}

function testGetAttributesFromCallStackReturnsEmptyWhenNoAttributes(): void
{
    $attributes = unmarkedFunction(CallStackAttribute::class);

    Assert::same($attributes, []);
}

function testGetAttributesFromCallStackReturnsEmptyWhenNoMatching(): void
{
    $obj = new CallStackTestClass();
    $attributes = $obj->methodA(MarkerAttribute::class);

    Assert::same($attributes, []);
}

function testGetAttributesFromCallStackWithNullAttributeClass(): void
{
    $attributes = topLevelFunction(null);

    // Should return all attributes, not just CallStackAttribute
    Assert::true(\count($attributes) >= 1);
}

function testGetAttributesFromCallStackIncludesClassAttributes(): void
{
    $obj = new CallStackTestClass();
    $attributes = $obj->methodA(CallStackAttribute::class, true, true);

    $labels = \array_map(
        static fn(\ReflectionAttribute $attr) => $attr->newInstance()->label,
        $attributes,
    );

    // Should find both method attribute and class attribute
    Assert::true(\in_array('methodA', $labels, true));
    Assert::true(\in_array('classAttribute', $labels, true));
}

function testGetAttributesFromCallStackWithoutClassAttributes(): void
{
    $obj = new CallStackTestClass();
    $attributes = $obj->methodA(CallStackAttribute::class, true, false);

    $labels = \array_map(
        static fn(\ReflectionAttribute $attr) => $attr->newInstance()->label,
        $attributes,
    );

    // Should find only method attribute, not class attribute
    Assert::true(\in_array('methodA', $labels, true));
    Assert::false(\in_array('classAttribute', $labels, true));
}

function testGetAttributesFromCallStackIncludesParentClassAttributes(): void
{
    $obj = new CallStackChildClass();
    $attributes = $obj->childMethod(CallStackAttribute::class, true, true, true);

    $labels = \array_map(
        static fn(\ReflectionAttribute $attr) => $attr->newInstance()->label,
        $attributes,
    );

    // Should find child class and parent class attributes
    Assert::true(\in_array('childClassAttribute', $labels, true));
    Assert::true(\in_array('baseClassAttribute', $labels, true));
}

function testGetAttributesFromCallStackWithoutParentClassAttributes(): void
{
    $obj = new CallStackChildClass();
    $attributes = $obj->childMethod(CallStackAttribute::class, true, true, false);

    $labels = \array_map(
        static fn(\ReflectionAttribute $attr) => $attr->newInstance()->label,
        $attributes,
    );

    // Should find only child class attribute, not parent
    Assert::true(\in_array('childClassAttribute', $labels, true));
    Assert::false(\in_array('baseClassAttribute', $labels, true));
}

function testGetAttributesFromCallStackWithLimit(): void
{
    $obj = new CallStackTestClass();
    $attributes = $obj->methodB(CallStackAttribute::class, true, false, true, true, 1);

    // Should return only 1 attribute due to limit
    Assert::count($attributes, 1);
}

function testGetAttributesFromCallStackWithLimitGreaterThanResults(): void
{
    $attributes = topLevelFunction(CallStackAttribute::class, true, false, true, true, 100);

    // Should return all available attributes (less than limit)
    Assert::count($attributes, 1);
}

function testGetAttributesFromCallStackWithLimitAndNestedCalls(): void
{
    $obj = new CallStackTestClass();
    $attributes = $obj->methodB(CallStackAttribute::class, true, false, true, true, 2);

    // Should return exactly 2 attributes
    Assert::count($attributes, 2);

    $labels = \array_map(
        static fn(\ReflectionAttribute $attr) => $attr->newInstance()->label,
        $attributes,
    );

    // Should find the first two attributes from the call stack
    Assert::true(\in_array('methodA', $labels, true));
    Assert::true(\in_array('methodB', $labels, true));
}

// --- fetchFunctionAttributes: mergePolicy ---

function testMergeAllReturnsBothLayers(): void
{
    $ref = new \ReflectionMethod(MergePolicyChild::class, 'methodWithAttrOnBothLayers');

    $attrs = Reflection::fetchFunctionAttributes($ref, mergePolicy: Reflection::MERGE_ALL);

    // Child has AnotherMarkerAttribute, parent has MarkerAttribute
    Assert::count($attrs, 2);
}

function testMergeFirstReturnsChildLayerOnly(): void
{
    $ref = new \ReflectionMethod(MergePolicyChild::class, 'methodWithAttrOnBothLayers');

    $attrs = Reflection::fetchFunctionAttributes($ref, mergePolicy: Reflection::MERGE_FIRST);

    // Child has AnotherMarkerAttribute — first non-empty layer
    Assert::count($attrs, 1);
    Assert::same($attrs[0]->getName(), AnotherMarkerAttribute::class);
}

function testMergeLastReturnsParentLayerOnly(): void
{
    $ref = new \ReflectionMethod(MergePolicyChild::class, 'methodWithAttrOnBothLayers');

    $attrs = Reflection::fetchFunctionAttributes($ref, mergePolicy: Reflection::MERGE_LAST);

    // Parent has MarkerAttribute — last non-empty layer
    Assert::count($attrs, 1);
    Assert::same($attrs[0]->getName(), MarkerAttribute::class);
}

function testMergeFirstSkipsEmptyChildGoesToParent(): void
{
    $ref = new \ReflectionMethod(MergePolicyChild::class, 'methodWithAttrOnParentOnly');

    $attrs = Reflection::fetchFunctionAttributes($ref, mergePolicy: Reflection::MERGE_FIRST);

    // Child has no attrs, parent has MarkerAttribute
    Assert::count($attrs, 1);
    Assert::same($attrs[0]->getName(), MarkerAttribute::class);
}

function testMergeLastSkipsEmptyParentUsesChild(): void
{
    $ref = new \ReflectionMethod(MergePolicyChild::class, 'methodWithAttrOnChildOnly');

    $attrs = Reflection::fetchFunctionAttributes($ref, mergePolicy: Reflection::MERGE_LAST);

    // Child has MarkerAttribute, parent has nothing
    Assert::count($attrs, 1);
    Assert::same($attrs[0]->getName(), MarkerAttribute::class);
}

function testMergePolicyReturnsEmptyWhenNoAttrs(): void
{
    $ref = new \ReflectionMethod(MergePolicyChild::class, 'methodWithNoAttr');

    Assert::same(Reflection::fetchFunctionAttributes($ref, mergePolicy: Reflection::MERGE_FIRST), []);
    Assert::same(Reflection::fetchFunctionAttributes($ref, mergePolicy: Reflection::MERGE_LAST), []);
    Assert::same(Reflection::fetchFunctionAttributes($ref, mergePolicy: Reflection::MERGE_ALL), []);
}

// --- fetchFunctionAttributes: limit ---

function testLimitReturnsCappedResults(): void
{
    $ref = new \ReflectionMethod(MergePolicyChild::class, 'methodWithAttrOnBothLayers');

    $attrs = Reflection::fetchFunctionAttributes($ref, limit: 1, mergePolicy: Reflection::MERGE_ALL);

    Assert::count($attrs, 1);
}

function testLimitWithMergeFirstCapsResults(): void
{
    $ref = new \ReflectionMethod(MergePolicyChild::class, 'methodWithAttrOnBothLayers');

    // MERGE_FIRST returns only first layer — 1 attribute, limit 5 won't exceed
    $attrs = Reflection::fetchFunctionAttributes($ref, limit: 5, mergePolicy: Reflection::MERGE_FIRST);

    Assert::count($attrs, 1);
}

function testLimitWithMergeLastCapsResults(): void
{
    $ref = new \ReflectionMethod(MergePolicyChild::class, 'methodWithAttrOnBothLayers');

    $attrs = Reflection::fetchFunctionAttributes($ref, limit: 1, mergePolicy: Reflection::MERGE_LAST);

    Assert::count($attrs, 1);
}

function testGetAttributesFromCallStackDuplicatesClassAttributesFromHierarchy(): void
{
    $obj = new CallStackChildClass();
    // Call stack: childMethod() -> overriddenMethod()
    // With includeClasses=true and includeParents=true:
    // - overriddenMethod scans CallStackChildClass + CallStackBaseClass
    // - childMethod scans CallStackChildClass + CallStackBaseClass
    // Result: childClassAttribute appears twice, baseClassAttribute appears twice
    $attributes = $obj->childMethod(CallStackAttribute::class, true, true, true);

    $labels = \array_map(
        static fn(\ReflectionAttribute $attr) => $attr->newInstance()->label,
        $attributes,
    );

    // Count occurrences of each label
    $childClassCount = \count(\array_filter($labels, static fn($l) => $l === 'childClassAttribute'));
    $baseClassCount = \count(\array_filter($labels, static fn($l) => $l === 'baseClassAttribute'));

    // Both class attributes should appear twice (once per method in call stack)
    Assert::same($childClassCount, 2);
    Assert::same($baseClassCount, 2);
}
