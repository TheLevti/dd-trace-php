#include "handlers_api.h"

// This file is compiled by both the tracer and profiler.

void datadog_php_install_handler(datadog_php_zif_handler handler) {
    zend_function *old_handler;
    old_handler = zend_hash_str_find_ptr(CG(function_table), handler.name, handler.name_len);
    if (old_handler != NULL) {
        *handler.old_handler = old_handler->internal_function.handler;
        old_handler->internal_function.handler = handler.new_handler;
    }
}

void datadog_php_install_method_handler(datadog_php_zim_handler handler) {
    zend_function *old_handler;
    zend_class_entry *ce = zend_hash_str_find_ptr(CG(class_table), handler.class_name, handler.class_name_len);
    if (ce != NULL) {
        old_handler = zend_hash_str_find_ptr(&ce->function_table, handler.name, handler.name_len);
        if (old_handler != NULL) {
            *handler.old_handler = old_handler->internal_function.handler;
            old_handler->internal_function.handler = handler.new_handler;
        }
    }
}
