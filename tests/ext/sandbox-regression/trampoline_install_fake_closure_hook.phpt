--TEST--
Test installing hook on trampoline fake closure
--FILE--
<?php

class A {
    private function shadow() {
        print "Shadowed!\n";
    }

    function __call($a, $b) {
        print "Invoked $a\n";
    }
}

$x = (new A)->test(...);
DDTrace\install_hook($x, function($hook) {
    echo "Hooked\n";
});

$x();
(new A)->test();

DDTrace\install_hook((new A)->shadow(...), function($hook) {
    echo "Shadow hooked\n";
});
(new A)->shadow();

?>
--EXPECT--
Hooked
Invoked test
Hooked
Invoked test
Hooked
Shadow hooked
Invoked shadow
