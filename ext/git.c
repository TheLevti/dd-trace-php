#include "git.h"
#include "configuration.h"
#include "ddtrace.h"
#include <SAPI.h>
#include <string.h>

#ifdef PHP_WIN32
#include <direct.h>
#define getcwd _getcwd
#endif

ZEND_EXTERN_MODULE_GLOBALS(ddtrace);

#ifndef PATH_MAX
#define PATH_MAX 4096
#endif

struct _git_metadata {
    bool available;
    zend_string *property_commit;
    zend_string *property_repository;
};

int remove_trailing_newline(char *str) {
    size_t len = strlen(str);
    while (len > 0 && (str[len - 1] == '\n' || str[len - 1] == '\r')) {
        len--;
    }
    str[len] = '\0';
    return len;
}

void normalize_string(zend_string *str) {
    ZSTR_LEN(str) = remove_trailing_newline(ZSTR_VAL(str));
}

zend_string *read_git_file(const char *path, bool persistent) {
    FILE *file = fopen(path, "r");
    if (!file) {
        return NULL;
    }

    char buffer[256];
    size_t len = fread(buffer, 1, sizeof(buffer) - 1, file);
    fclose(file);

    if (len == 0) {
        return NULL;
    }

    buffer[len] = '\0';
    len = remove_trailing_newline(buffer);
    return zend_string_init(buffer, len, persistent);
}

zend_string *get_commit_sha(const char *git_dir) {
    char head_path[PATH_MAX];
#ifndef _WIN32
    snprintf(head_path, sizeof(head_path), "%s/HEAD", git_dir);
#else
    snprintf(head_path, sizeof(head_path), "%s\\HEAD", git_dir);
#endif

    zend_string *head_content = read_git_file(head_path, false);
    if (!head_content) {
        return NULL;
    }

    const char *ref_prefix = "ref: ";
    if (strncmp(ZSTR_VAL(head_content), ref_prefix, strlen(ref_prefix)) == 0) {
        char ref_path[PATH_MAX];
#ifndef _WIN32
        snprintf(ref_path, sizeof(ref_path), "%s/%s", git_dir, ZSTR_VAL(head_content) + strlen(ref_prefix));
#else
        snprintf(ref_path, sizeof(ref_path), "%s\\%s", git_dir, ZSTR_VAL(head_content) + strlen(ref_prefix));
#endif
        zend_string_release(head_content);
        return read_git_file(ref_path, true);
    }

    return head_content;
}

zend_string *get_repository_url(const char *git_dir) {
    char config_path[PATH_MAX];
    snprintf(config_path, sizeof(config_path), "%s/config", git_dir);

    FILE *file = fopen(config_path, "r");
    if (!file) {
        return NULL;
    }

    char buffer[256];
    zend_string *result = NULL;
    const char *url_prefix = "url = ";

    while (fgets(buffer, sizeof(buffer), file)) {
        if (strncmp(buffer, "[remote \"origin\"]", 17) == 0) {
            while (fgets(buffer, sizeof(buffer), file)) {
                if (buffer[0] == '[') break;
                char *url = strstr(buffer, url_prefix);
                if (url) {
                    result = zend_string_init(url + strlen(url_prefix), strlen(url) - strlen(url_prefix), 1);
                    normalize_string(result);
                    break;
                }
            }
            break;
        }
    }

    fclose(file);
    return result;
}

zend_string *find_git_dir(const char *start_dir) {
    char current_dir[PATH_MAX];
    snprintf(current_dir, sizeof(current_dir), "%s", start_dir);

    while (true) {
        char git_dir[PATH_MAX];
#ifndef _WIN32
        snprintf(git_dir, sizeof(git_dir), "%s/.git", current_dir);
#else
        snprintf(git_dir, sizeof(git_dir), "%s\\.git", current_dir);
#endif

        if (access(git_dir, F_OK) == 0) {
            return zend_string_init(git_dir, strlen(git_dir), 0);
        }

#ifndef _WIN32
        char *last_slash = strrchr(current_dir, '/');
#else
        char *last_slash = strrchr(current_dir, '\\');
#endif
        if (!last_slash) {
            return NULL;
        }

        *last_slash = '\0';
    }
}

void remove_credentials(zend_string *repo_url) {
    char *url = ZSTR_VAL(repo_url);
    char *at = strchr(url, '@');
    if (at != NULL) {
        char *start = strstr(url, "://");
        if (start != NULL) {
            start += 3; // Move pointer past "://"
            size_t remaining_length = strlen(at + 1);
            memmove(start, at + 1, remaining_length + 1);
            ZSTR_LEN(repo_url) = (start - url) + remaining_length;
        }
    }
}

bool add_git_info(zval *carrier, zend_string *commit_sha, zend_string *repository_url) {
    if (commit_sha && repository_url && ZSTR_LEN(commit_sha) > 0 && ZSTR_LEN(repository_url) > 0) {
        remove_credentials(repository_url);
        object_init_ex(carrier, ddtrace_ce_git_metadata);
        ddtrace_git_metadata *git_metadata = (ddtrace_git_metadata *) Z_OBJ_P(carrier);
        ZVAL_STR(&git_metadata->property_commit, commit_sha);
        ZVAL_STR(&git_metadata->property_repository, repository_url);
        return true;
    }

    return false;
}

bool inject_from_env(zval *carrier) {
    zend_string *commit_sha = get_DD_GIT_COMMIT_SHA();
    zend_string *repository_url = get_DD_GIT_REPOSITORY_URL();
    return add_git_info(carrier, commit_sha, repository_url);
}

bool inject_from_global_tags(zval *carrier) {
    zend_array *global_tags = get_DD_TAGS();
    zval *commit_sha = zend_hash_str_find(global_tags, ZEND_STRL("git.commit.sha"));
    zval *repository_url = zend_hash_str_find(global_tags, ZEND_STRL("git.repository_url"));
    if (commit_sha && repository_url && Z_TYPE_P(commit_sha) == IS_STRING && Z_TYPE_P(repository_url) == IS_STRING) {
        return add_git_info(carrier, Z_STR_P(commit_sha), Z_STR_P(repository_url));
    }
    return false;
}

bool inject_from_git_dir(zval *carrier, zend_string *cwd) {
    zend_string *git_dir = find_git_dir(ZSTR_VAL(cwd));
    if (!git_dir) {
        return false;
    }

    zend_string *commit_sha = get_commit_sha(ZSTR_VAL(git_dir));
    zend_string *repository_url = get_repository_url(ZSTR_VAL(git_dir));
    bool success = add_git_info(carrier, commit_sha, repository_url);
    zend_string_release(git_dir);

    return success;
}

void cache_git_metadata(zend_string *cwd, zend_string *commit_sha, zend_string *repository_url) {
    struct _git_metadata *git_metadata = pemalloc(sizeof(struct _git_metadata), 1);
    git_metadata->available = commit_sha && repository_url;
    git_metadata->property_commit = commit_sha;
    git_metadata->property_repository = repository_url;
    zend_hash_add_ptr(&DDTRACE_G(git_metadata), cwd, git_metadata);
}

void ddtrace_inject_git_metadata(zval *git_metadata_zv) {
    zend_string *cwd = NULL;

    if (SG(options) & SAPI_OPTION_NO_CHDIR) {
        const char *script_filename = SG(request_info).path_translated;
#ifndef _WIN32
        const char *last_slash = strrchr(script_filename, '/');
#else
        const char *last_slash = strrchr(script_filename, '\\');
#endif
        if (last_slash) {
            cwd = zend_string_init(script_filename, last_slash - script_filename, 1);
        } else {
            cwd = zend_string_init(ZEND_STRL("."), 1);
        }
    } else {
        char buffer[PATH_MAX];
        if (getcwd(buffer, sizeof(buffer)) == NULL) {
            return;
        }
        cwd = zend_string_init(buffer, strlen(buffer), 1);
    }

    struct _git_metadata *entry = zend_hash_find_ptr(&DDTRACE_G(git_metadata), cwd);

    if (entry) {
        if (entry->available) {
            zend_string_addref(entry->property_commit);
            zend_string_addref(entry->property_repository);
            add_git_info(git_metadata_zv, entry->property_commit, entry->property_repository);
        }
    } else if (inject_from_env(git_metadata_zv) || inject_from_global_tags(git_metadata_zv) || inject_from_git_dir(git_metadata_zv, cwd)) {
        ddtrace_git_metadata *git_metadata_obj = (ddtrace_git_metadata *) Z_OBJ_P(git_metadata_zv);
        zend_string *property_commit = Z_STR(git_metadata_obj->property_commit);
        zend_string *property_repository = Z_STR(git_metadata_obj->property_repository);
        zend_string_addref(property_commit);
        zend_string_addref(property_repository);
        cache_git_metadata(cwd, property_commit, property_repository);
    } else {
        cache_git_metadata(cwd, NULL, NULL);
    }

    zend_string_release(cwd);
}

void ddtrace_clean_git_metadata(void) {
    struct _git_metadata *val;
    ZEND_HASH_FOREACH_PTR(&DDTRACE_G(git_metadata), val) {
        zend_string_release(val->property_commit);
        zend_string_release(val->property_repository);
        pefree(val, 1);
    } ZEND_HASH_FOREACH_END();
    zend_hash_destroy(&DDTRACE_G(git_metadata));
}