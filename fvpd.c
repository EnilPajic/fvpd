/**
   FVPD.C (fast) v1.0 - 4. 7. 2018
   by Vedran Ljubovic
   Compile with: gcc fvpd.c -o fvpd -lm -lpcre
**/


#include <stdio.h>
#include <pcre.h>
#include <ctype.h>
#include <string.h>
#include <dirent.h>
#include <math.h>

#define LINES_ADDED 0
#define AVG_PASTE 1
#define AVG_TEST 2
#define COMPILED_FAILS 3
#define COMPILED_SUCCESS 4
#define LINES_DELETED 5
#define MAX_PASTE_LEN 6
#define LINES_MODIFIED 7
#define NUM_PASTES 8
#define TS_SPEED 9
#define TESTINGS 10
#define WORK_TIME 11
#define SHORT_BREAKS 12
#define LONG_BREAKS 13
#define TIME_FROM_DEADLINE 14

#define ALL_NUMBERS 15
#define NUM_ARRAYS 16
#define AVG_LINE_LEN 17
#define NUM_BLOCKS 18
#define NUM_BRANCHES 19
#define NUM_CHARS 20
#define COMP_OP 21
#define NUM_FUN 22
#define NUM_INCLUDE 23
#define NUM_ARRAY_ACCESS 24
#define NUM_LITERALS 25
#define LOC 26
#define NUM_LOOPS 27
#define MAIN_LEN 28
#define NUM_MLCOMMENTS 29
#define NUM_MODULO_INCDEC_OP 30
#define MULDIV_OP 31
#define NEG_OP 32
#define NUMBERS 33
#define NUM_BRACES 34
#define NUM_COMMAS 35
#define PLUSMINUS_OP 36
#define RARE_OP 37
#define EQUAL_OP 38
#define NUM_SLCOMMENTS 39
#define SLOC 40
#define SLOC_CMD 41
#define ORAND_OP 42
#define NUM_STRING 43
#define NUM_TRYS 44
#define NUM_VARS 45
#define NUM_WORDS 46

#define NUM_REGEXES 23
#define NUM_FEATURES 47
#define NUM_SUBSTRS 3000
#define BUFFER_SIZE 10000
#define MAX_FILES 10000
#define MAX_LINE_LEN 1000
#define MAX_FILENAME 256
#define MAX_PATH 4096

#define DEFAULT_DISTANCE_THRESHOLD 0.01

//#define DEBUG_FILE "src/OR2016/Z5/Z2/fzukorlic1.c"


/* Regex stuff */

pcre* reCompiled[NUM_REGEXES];
pcre_extra* pcreExtra[NUM_REGEXES];

void prepare_regex(int n, const char* aStrRegex) {
    const char *pcreErrorStr;
    int pcreErrorOffset;
    
    int options = PCRE_MULTILINE;
    
    reCompiled[n] = pcre_compile(aStrRegex, options, &pcreErrorStr, &pcreErrorOffset, NULL);
    if(reCompiled[n] == NULL) {
        printf("ERROR: Could not compile '%s': %s\n", aStrRegex, pcreErrorStr);
        exit(1);
    }
    
    pcreExtra[n] = pcre_study(reCompiled[n], 0, &pcreErrorStr);
    if(pcreErrorStr != NULL) {
        printf("ERROR: Could not study '%s': %s\n", aStrRegex, pcreErrorStr);
        exit(1);
    }
}

void prepare_regexes() {
    prepare_regex(0, "\\/\\*(?:.|[\\r\\n])*?\\*\\/"); // regex_mcomment
    prepare_regex(1,/*regex_comment = */ "\\/\\/.*");
    prepare_regex(2,/*regex_word = */ "(\\w+)");
    prepare_regex(3,/*regex_loop = */ "\\b(for|while|goto)\\b");
    prepare_regex(4,/*regex_branching = */ "\\b(if|else\\s+if|else|switch|case|default)\\b");
    prepare_regex(5,/*regex_string = */ "\"(.*?[^\\\\])\"");
    prepare_regex(6,/*regex_charliteral = */ "'.*?[^\\\\]'");
    prepare_regex(7,/*regex_plusminus = */ "(?:\\+=|\\+|\\-=|\\-)");
    prepare_regex(8,/*regex_putadijeli = */ "(\\*=|\\*|\\/=|\\/)");
    prepare_regex(9,/*regex_modulaincdec = */ "(?:%=|%|\\+\\+|\\-\\-)");
    prepare_regex(10,/*regex_iili = */ "(&&|\\|\\||\\band\\b|\\bor\\b)");
    prepare_regex(11,/*regex_istirazliciti = */ "(==|\\!=|=|not_eq)");
    prepare_regex(12,/*regex_notneg = */ "(?:~|(?:!(?<!=))|\\bnot\\b|\\bcompl\\b)");
    prepare_regex(13,/*regex_compare = */ "(?:>=|>|<=|<)");
    prepare_regex(14,/*regex_rijetkiop = */ "(?:&=|\\|=|(?<!&)&(?!&)|(?<!\\|)\\|(?!\\|)|\\^=|\\^|&=|\\.\\*|\\-\\>|\\*\\*\\*\\*|\\*\\*\\*|\\.\\.\\.|\\.)");
    prepare_regex(15,/*regex_include = */ "#\\h*include\\h*");
    prepare_regex(16,/*regex_trycatch = */ "\\btry\\b");
    prepare_regex(17,/*regex_tipovi = */ "(?:(?:unsigned\\s+|signed\\s+)?(?:\\blong\\s+long\\s+int\\b|\\blong\\s+long\\b|\\blong\\b|\\bchar\\b|\\bshort\\s+int\\b|\\bshort\\b|\\bint\\b|\\bbool\\b))|\\blong double\\b|\\bdouble\\b|\\bfloat\\b|\\bFILE\\b|\\bunsigned\\b|\\bsigned\\b");
    prepare_regex(18,/*regex_fun = */ "([a-zA-Z0-9_>]+)\\s*\\*{0,5}\\s+[a-zA-Z0-9_>]+\\s*?\\(.*\\)\\s*{");
    prepare_regex(19,/*regex_nizovi = */ "[a-zA-Z0-9_>]+(?<!else)(?:\\**\\s+\\**\\s*|\\*+|,)[a-zA-Z0-9_>]+\\s*\\[\\s*(?:\\d{0,12}|.*?)\\s*\\]");
    prepare_regex(20,/*regex_svibrojevi = */ "\\d{1,50}");
    prepare_regex(21,/*regex_brojevi = */ "\\b\\d+\\b");
    prepare_regex(22,/*regex za main = */ "\\bint\\s+main\\s*\\(");
}

int do_regex(int regex, char* bugger) {
    int subStrVec[NUM_SUBSTRS], i;
    int pcreExecRet, pos = 0, count = 0;
    do {
        pcreExecRet = pcre_exec(reCompiled[regex], pcreExtra[regex], bugger, strlen(bugger), pos, 0, subStrVec, NUM_SUBSTRS);
        if (pcreExecRet <= 0) {
            switch(pcreExecRet) {
                case PCRE_ERROR_NOMATCH      : /*printf("String did not match the pattern\n"); */   break;
                case PCRE_ERROR_NULL         : printf("Something was null\n");                      break;
                case PCRE_ERROR_BADOPTION    : printf("A bad option was passed\n");                 break;
                case PCRE_ERROR_BADMAGIC     : printf("Magic number bad (compiled re corrupt?)\n"); break;
                case PCRE_ERROR_UNKNOWN_NODE : printf("Something kooky in the compiled re\n");      break;
                case PCRE_ERROR_NOMEMORY     : printf("Ran out of memory\n");                       break;
                default                      : printf("Unknown error\n");                           break;
            } /* end switch */
            return count;
        }
        
        count++;
        pos = subStrVec[1];
    } while(pcreExecRet > 0);
    return count;
}


/* Helper functions */

void mstrcpy(char* p, char* q) {
    while (*q) *p++=*q++;
    *p = '\0';
}

int strword(char* s, char* substr) {
    int word=1;
    char *p, *q;
    while (*s != '\0') {
        if (word == 1 && *s == *substr) {
            p=s;
            q=substr;
            while (*q != '\0' && *p++ == *q++);
            if (*q == '\0' && (*p == '\0' || isspace(*p))) return 1;
        }
        word = isspace(*s++);
    }
    return 0;
}

int isnumber(char* s) {
    while (*s != '\0') {
        if (!isdigit(*s)) return 0;
        s++;
    }
    return 1;
}


/* New feature extraction method using state machine */

void extract_features_fast(const char* filename, double* fv) {
    FILE* fp = fopen(filename, "r");
    int i, c = ' ' /* current char */, oldc /* previous char */, mlc=0 /* in multiline comment */, slc=0 /* in singleline comment */, 
        strlit=0 /* in string literal */, chlit=0 /* in char literal */, skip1=0 /* skip next char */, linelen=0 /* length of current line */, 
        wordlen=0 /* length of current word */, decl=0 /* this is a variable/function declaration statement */, 
        inmain=0 /* in main function */, openbraces=0 /* how many open braces */, asterisks=0 /* >2 asterisk chars (a rare op) */, 
        dots=0 /* two dots (a rare op) */, mightbefunction=0 /* seems to be a function declaration */, 
        notempty=0 /* current line is not empty */, lines=0 /* total number of lines */;

    char line[MAX_LINE_LEN];
    char word[MAX_LINE_LEN];
    char *s;
    if (!fp) {
        printf("Can't open %s\n", filename);
        return;
    }
    
    for (i=0; i<NUM_FEATURES; i++) fv[i]=0;
    
    while ((oldc = c, c = fgetc(fp)) != EOF) {
        if (skip1 == 1) {
            skip1=0;
            continue;
        }
        
        /* Multiline comment */
        if (mlc == 0 && slc == 0 && strlit == 0 && chlit == 0 && oldc == '/' && c == '*') {
            fv[NUM_MLCOMMENTS]++;
            mlc=1;
        }
        else if (mlc == 1 && oldc == '*' && c == '/') {
            mlc=0;
            //skip1=1; // Following is not a slc: /* foo *// bar
            continue;
        }
        if (mlc == 1) continue;
        
        /* Singleline comment */
        if (slc == 0 && strlit == 0 && chlit == 0 && oldc == '/' && c == '/') {
            fv[NUM_SLCOMMENTS]++;
            slc=1;
        }
        else if (slc == 1 && c == '\n') {
            slc=0;
        }
        if (slc == 1) continue;
        
        /* String literal */
        if (chlit == 0 && oldc != '\\' && c == '"') {
            if (strlit == 0) {
                fv[NUM_STRING]++;
                strlit=1;
            } else {
                strlit=0;
            }
            continue;
        }
        if (strlit == 1) continue;
        
        /* Char literal */
        if (oldc != '\\' && c == '\'') {
            if (chlit == 0) {
                fv[NUM_LITERALS]++;
                chlit=1;
            } else {
                chlit=0;
            }
            continue;
        }
        if (chlit == 1) continue;
        
        /* Arrays */
        if (c == '[' && oldc != ']') {
            if (decl == 1)
                fv[NUM_ARRAYS]++;
            else
                fv[NUM_ARRAY_ACCESS]++;
        }
        
        if (c == '{') decl=0;
        
        /* All numbers */
        if (isdigit(c) && !isdigit(oldc)) {
            fv[ALL_NUMBERS]++;
            word[wordlen] = '\0';
        }
        
        /* Operators */
        if ((oldc == '=' || oldc == '!') && c == '=')
            fv[EQUAL_OP]++;
        if (oldc == '!' && c != '=')
            fv[NEG_OP]++;
        if ((oldc == '+' || oldc == '-') && (c == '=' || isalnum(c) || c == '_' || c == '(' || isspace(c)))
            fv[PLUSMINUS_OP]++;
        if ((oldc == '*' || oldc == '/') && (c == '=' || isalnum(c) || c == '_' || c == '(' || isspace(c)))
            fv[MULDIV_OP]++;
        if (oldc == '%' && (c == '=' || isalnum(c) || c == '_' || c == '(' || isspace(c)))
            fv[NUM_MODULO_INCDEC_OP]++;
        if ((oldc == '+' && c == '+') || (oldc == '-' && c == '-'))
            fv[NUM_MODULO_INCDEC_OP]++;
        if ((oldc == '&' && c == '&') || (oldc == '|' && c == '|'))
            fv[ORAND_OP]++;
        if ((oldc == '>' || oldc == '<') && (c == '=' || isalnum(c) || c == '_' || c == '(' || isspace(c))) {
            fv[COMP_OP]++;
        }
        if ((oldc == '&' && c != '&') || (oldc == '|' && c != '|'))
            fv[RARE_OP]++;
        if (c == '^' || (oldc == '.' && c == '*') || (oldc == '-' && c == '>'))
            fv[RARE_OP]++;
        if (oldc == '*' && c == '*')
            if (asterisks == 0)
                asterisks = 1;
            else {
                asterisks = 0;
                fv[RARE_OP]++;
            }
        if (oldc == '.' && c == '.')
            if (dots == 0)
                dots = 1;
            else {
                dots = 0;
                fv[RARE_OP]++;
            }
        
        /* Number of functions */
        if (decl && c == '(')
            mightbefunction = 1;
        if (mightbefunction == 1 && c == ')')
            mightbefunction = 2;
        if (mightbefunction == 2 && c == '{') {
            fv[NUM_FUN]++;
            mightbefunction = 0;
        }
        
        /* Commands */
        if (c == ';') {
            mightbefunction = 0;
            decl = 0;
            fv[SLOC_CMD]++;
        }
        
        /* Blocks, commas, braces, chars */
        if (c == '}') fv[NUM_BLOCKS]++;
        if (c == ',') fv[NUM_COMMAS]++;
        if (c == ')') fv[NUM_BRACES]++;
        if (!isspace(c)) {
            fv[NUM_CHARS]++;
            if (c != '{' && c != '}') notempty=1;
        }
        
        /* Handle words (word is any sequence of identifier-chars) */
        if (isalnum(c) || c == '_') {
            word[wordlen++] = c;
            if (wordlen >= MAX_LINE_LEN) wordlen--;
        } else {
            word[wordlen] = '\0';
            //if (wordlen>0) printf("Gotova rijec: %s %d\n", word, wordlen);
            wordlen = 0;
            
            /* Special word-like operators */
            if (!strcmp(word, "not") || !strcmp(word, "compl"))
                fv[NEG_OP]++;
            if (!strcmp(word, "not_eq"))
                fv[EQUAL_OP]++;
            if (!strcmp(word, "or") || !strcmp(word, "and"))
                fv[ORAND_OP]++;
            
            /* Try-catch */
            if (!strcmp(word, "try"))
                fv[NUM_TRYS]++;
            
            /* Used to calculate main length */
            if (!strcmp(word, "main"))
                inmain=1;
            
            /* Variable declaration */
            if (!strcmp(word, "signed") || !strcmp(word, "long") || !strcmp(word, "int") || !strcmp(word, "double") || !strcmp(word, "float") || !strcmp(word, "char") || !strcmp(word, "bool") || !strcmp(word, "FILE")) {
                fv[NUM_VARS]++;
                decl=1;
            }
            
            /* Loops */
            if (!strcmp(word, "for") || !strcmp(word, "while") || !strcmp(word, "goto"))
                fv[NUM_LOOPS]++;
            
            /* Branches */
            if ((!strcmp(word, "if") && !strstr(line,"else ")) || !strcmp(word, "else") || !strcmp(word, "switch") || !strcmp(word, "case") || !strcmp(word, "default"))
                fv[NUM_BRANCHES]++;
            
            /* Includes */
            if (!strcmp(word, "include") && strchr(line, '#'))
                fv[NUM_INCLUDE]++;
            
            /* "Words" */
            if (isalnum(oldc) || oldc == '_')
                fv[NUM_WORDS]++;
            
            /* Numbers */
            if (isnumber(word))
                fv[NUMBERS]++;
        }
        
        /* Main function length */
        if (inmain == 1) {
            if (!isspace(c)) fv[MAIN_LEN]++;
            if (c == '{')
                openbraces++;
            if (c == '}')
                openbraces--;
            if (openbraces == 0) inmain=0;
        }
            
        
        /* Add to line */
        if (c == '\n') {
            fv[LOC]++;
            if (notempty) fv[SLOC]++;
            fv[AVG_LINE_LEN] += linelen;
            
            linelen=0;
            lines++;
            //printf("Linija %d: %s\n", lines, line);
            line[0] = '\0';
            notempty=0;
        } else {
            line[linelen++] = c;
            line[linelen] = '\0';
            if (linelen >= MAX_LINE_LEN-1) linelen--;
        }
    } 
    fclose(fp);
    
    /* Finish counting words */
    if (isalnum(oldc) || oldc == '_')
        fv[NUM_WORDS]++;
    /* Finish counting lines */
    fv[AVG_LINE_LEN] /= lines;
}



/* Old feature extraction using regexes (a bit slow) */

void extract_features(const char* filename, double* fv) {
    char bugger[BUFFER_SIZE];
    FILE* fp = fopen(filename, "r");
    char* comment, *endcomment, *s, *eol;
    int i, bufLen;
    int subStrVec[30];
    int pcreExecRet;
    
    if (!fp) {
        printf("Can't open %s\n", filename);
        exit(0);
    }
    
    for (i=0; i<NUM_FEATURES; i++) fv[i]=0;
    do {
        bufLen=0;
        do {
            bugger[bufLen++] = fgetc(fp);
        } while (!feof(fp) && bufLen<BUFFER_SIZE);
        bugger[bufLen-1] = '\0';
        
        /* Multline comments - regex is broken */
        comment = strstr(bugger, "/*");
        while (comment) {
            endcomment = strstr(comment+2, "*/");
            if (!endcomment) break;
            fv[NUM_MLCOMMENTS]++;
            mstrcpy(comment, endcomment+2);
            comment = strstr(comment, "/*");
        }
        
        /* Singleline comments */
        comment = strstr(bugger, "//");
        while (comment) {
            endcomment = strchr(comment+2, '\n');
            fv[NUM_SLCOMMENTS]++;
                //printf("%d %d\n", comment-bugger, endcomment-bugger);
            if (!endcomment) {
                *comment = '\0';
                break;
            }
            mstrcpy(comment, endcomment);
            comment = strstr(comment, "//");
        }
        
        /* String literals */
        comment = strchr(bugger, '"');
        while (comment) {
            if (*(comment-1) == '\\') {
                comment = strchr(comment+1, '"');
            } else {
                do {
                    endcomment = strchr(comment+1, '"');
                    if (!endcomment) break;
                } while (*(endcomment-1) == '\\');
                if (!endcomment) break;
                mstrcpy(comment, endcomment+1);
                comment = strchr(comment, '"');
                fv[NUM_STRING]++;
            }
        }
        
        /* Match regexes */
        fv[NUM_LITERALS] = do_regex(6, bugger);
        fv[NUM_ARRAYS] = do_regex(19, bugger);
        fv[NUM_TRYS] = do_regex(16, bugger);
        fv[EQUAL_OP] = do_regex(11, bugger);
        fv[NEG_OP] = do_regex(12, bugger);
        fv[PLUSMINUS_OP] = do_regex(7, bugger);
        fv[MULDIV_OP] = do_regex(8, bugger);
        fv[NUM_MODULO_INCDEC_OP] = do_regex(9, bugger);
        fv[ORAND_OP] = do_regex(10, bugger);
        fv[COMP_OP] = do_regex(13, bugger);
        fv[RARE_OP] = do_regex(14, bugger);
        fv[NUM_WORDS] = do_regex(2, bugger);
        fv[NUMBERS] = do_regex(21, bugger);
        fv[ALL_NUMBERS] = do_regex(20, bugger);
        fv[NUM_LOOPS] = do_regex(3, bugger);
        fv[NUM_BRANCHES] = do_regex(4, bugger);
        fv[NUM_INCLUDE] = do_regex(15, bugger);
        fv[NUM_FUN] = do_regex(18, bugger);
        fv[NUM_VARS] = do_regex(17, bugger);
        
        /* Length of main function */
        pcreExecRet =  pcre_exec(reCompiled[22], pcreExtra[22], bugger, strlen(bugger), 0, 0, subStrVec, 30);
        if (pcreExecRet > 0) {
            s = bugger+subStrVec[0];
            int open_blocks = 0;
            while (*s != '\0' && *s != '{') s++;
            while (*s != '\0') {
                if (!isspace(*s)) fv[MAIN_LEN]++;
                if (*s == '{') open_blocks++;
                if (*s == '}') open_blocks--;
                if (open_blocks == 0) break;
                s++;
            }
        }
        
        /* Rest: line-by-line processing */
        s = bugger;
        eol = strchr(s, '\n');
        while (eol) {
            fv[LOC]++;
            
            if (s == eol) {
                s++;
                eol = strchr(s, '\n');
                continue;
            }
            
            /* Trim */
            char* p = s;
            while (p < eol && (*p == ' ' || *p == '\t')) p++;
            if (p == eol) { 
                s = eol+1;
                eol = strchr(s, '\n');
                continue;
            }
            if (p > s) {
                eol -= p-s;
                mstrcpy (s, p);
            }
            p = eol-1;
            while (p > s && (*p == ' ' || *p == '\t')) p--;
            if (p < s) {
                s = eol+1;
                eol = strchr(s, '\n');
                continue;
            } else if (p < eol-1) {
                mstrcpy(p+1, eol);
                eol = p+1;
            }

            /* Not SLOC */
            if ((*s == '{' || *s == '}') && s+1 == eol) {
                if (*s == '}') fv[NUM_BLOCKS]++;
                s += 2;
                eol = strchr(s, '\n');
                continue;
            }

            /* Remaining features */
            fv[SLOC]++;
            fv[AVG_LINE_LEN] += eol - s;
            while (s != eol) {
                if (*s == '}') fv[NUM_BLOCKS]++;
                else if (*s == ']') fv[NUM_ARRAY_ACCESS]++;
                else if (*s == ',') fv[NUM_COMMAS]++;
                else if (*s == ')') fv[NUM_BRACES]++;
                else if (*s == ';') fv[SLOC_CMD]++;
                else if (!isspace(*s)) fv[NUM_CHARS]++;
                s++;
            }
            s++;
            eol = strchr(s, '\n');
        }
        fv[NUM_ARRAY_ACCESS] -= fv[NUM_ARRAYS];
        if (fv[SLOC] > 0)
            fv[AVG_LINE_LEN] /= fv[SLOC];
    } while (!feof(fp));
    
    fclose(fp);
}



/* Calculate distance between two feature vectors */

double distance(double* fv1, double* fv2, double* weights) {
    double result = 0;
    int i;
    for (i=0; i<NUM_FEATURES; i++) {
        if (weights[i] == 0) continue;
        //if (weights[i] < 0)
            //result -= weights[i] * weights[i] * ((fv1[i] - fv2[i]) * (fv1[i] - fv2[i]));
        //else
            result += weights[i] * weights[i] * ((fv1[i] - fv2[i]) * (fv1[i] - fv2[i]));
        //printf("%d: %g\n", i, result);
    }
    if (result < 0)
        return -sqrt(-result);
    return sqrt(result);
}



int main(int argc, char** argv) {
    double** fv;
    char** filenames;
    double dist, max, min;
    char fullpath[MAX_PATH];
    int nrFiles=0, i, j, pathLen;
    
    DIR* dir;
    struct dirent* dp;
    
    /* Optimum weights determined using GA */    
    double weights[] = {
        0,0,0,0,0,0,0,0,0,0,0,0,0,0,0, 0.189, 0.174, 0, 0.642, 1.445, 1.071, 1.079, 0.651, 0.082, 0.828,
        0.493, 0.087, 0.753, 0, 0.064, 0.686, 1.125, 0.648, 1.489, 0.536, 0.956, 1.356, 1.778, 0.843, 0, 0.106, 0.718,
        0.429, 0, 0.241, 0.690, 0
    };
    double distanceThreshold = DEFAULT_DISTANCE_THRESHOLD;
    
    /* Allocate arrays */
    /* TODO: Use less ram by allocating inside readdir loop? */
    fv = (double**)malloc(sizeof(double*) * MAX_FILES);
    filenames = (char**)malloc(sizeof(char*) * MAX_FILES);
    for (i=0; i<MAX_FILES; i++) {
        fv[i] = (double*)malloc(sizeof(double) * NUM_FEATURES);
        filenames[i] = (char*)malloc(sizeof(char) * MAX_FILENAME);
    }
    
#ifdef DEBUG_FILE
                //prepare_regexes();
                extract_features_fast(DEBUG_FILE, fv[0]);
                for (i=0; i<NUM_FEATURES; i++)
                    printf("[%d]=%g ",i, fv[0][i]);
                printf("\n");
                exit(0);
#endif
    
    if (argc == 1) {
        printf("fvpd.c v1.0 2018-04-09\n\nUsage:\n\tfvpd path [threshold]\n\n");
        exit(1);
    }
    if (argc > 2)
        sscanf(argv[2], "%lf", &distanceThreshold);
    
    //prepare_regexes();
    dir = opendir(argv[1]);
    if (!dir) {
        printf("Failed to open dir %s.\n", argv[1]);
    }
    
    strcpy(fullpath, argv[1]);
    pathLen = strlen(fullpath);
    if (fullpath[pathLen - 1] != '/') {
        fullpath[pathLen++] = '/';
        fullpath[pathLen] = '\0';
    }
    
    while((dp = readdir(dir)) != NULL) {
        if ( !strcmp(dp->d_name, ".") || !strcmp(dp->d_name, "..") ) continue;
        mstrcpy(filenames[nrFiles], dp->d_name);
        mstrcpy(fullpath+pathLen, dp->d_name);
        
        extract_features_fast(fullpath, fv[nrFiles++]);
    }
    
    /* Normalize feature vectors */
    for (i=15; i<NUM_FEATURES; i++) {
        max=min=fv[0][i];
        for (j=1; j<nrFiles; j++) {
            if (fv[j][i] < min) min = fv[j][i];
            if (fv[j][i] > max) max = fv[j][i];
        }
        if (max > min) for (j=0; j<nrFiles; j++) {
            fv[j][i] = (fv[j][i] - min) / (max - min);
        }
    }
    
    /* Find matching pairs */
    for (i=0; i<nrFiles; i++) {
        for (j=i+1; j<nrFiles; j++) {
            dist = distance(fv[i], fv[j], weights);
            if (dist < distanceThreshold)
                printf("%s-%s: %g\n", filenames[i], filenames[j], dist);
        }
    }

    
    /* Free */
    for (i=0; i<MAX_FILES; i++) {
        free(fv[i]);
        free(filenames[i]);
    }
    free(fv);
    free(filenames);

    

    return 0;
}
