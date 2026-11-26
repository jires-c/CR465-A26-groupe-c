<?php
// Complement facultatif inspire de V4 : sections, pages et quiz d introduction.
define('CLI_SCRIPT', true);
require '/var/www/html/config.php';
require_once($CFG->dirroot . '/course/lib.php');
\core\session\manager::set_user(get_admin());
$course = $DB->get_record('course', ['shortname' => 'CYBER465'], '*', MUST_EXIST);
// Creation des sections du cours.
course_create_sections_if_missing($course, [1, 2, 3]);

$sections = [
    1 => 'Introduction a la cybersecurite',
    2 => 'Menaces et bonnes pratiques',
    3 => 'Evaluation des connaissances'
];

foreach ($sections as $number => $name) {
    $section = $DB->get_record('course_sections', [
        'course' => $course->id,
        'section' => $number
    ], '*', MUST_EXIST);

    course_update_section($course, $section, [
        'name' => $name
    ]);
}

echo "Sections du cours configurees.\n";

// Creation des pages de formation.
require_once($CFG->dirroot . '/course/modlib.php');

$pages = [
    [
        'section' => 1,
        'name' => 'Les fondamentaux de la cybersecurite',
        'content' => '<p>La cybersecurite repose sur trois principes : confidentialite, integrite et disponibilite.</p>'
    ],
    [
        'section' => 2,
        'name' => 'Menaces et bonnes pratiques',
        'content' => '<p>Les principales menaces sont le phishing, les logiciels malveillants et les rancongiciels. Utilisez des mots de passe robustes, le MFA et les mises a jour regulieres.</p>'
    ]
];

foreach ($pages as $page) {
    if (!$DB->record_exists('page', [
        'course' => $course->id,
        'name' => $page['name']
    ])) {
        $module = new stdClass();
        $module->modulename = 'page';
        $module->module = $DB->get_field('modules', 'id', [
            'name' => 'page'
        ]);
        $module->course = $course->id;
        $module->section = $page['section'];
        $module->name = $page['name'];
        $module->intro = '';
        $module->introformat = FORMAT_HTML;
        $module->content = $page['content'];
        $module->contentformat = FORMAT_HTML;
        $module->visible = 1;
        $module->display = 5;

        add_moduleinfo($module, $course);
    }
}

echo "Pages de formation configurees.\n";

// Creation du quiz.
$quizname = 'Evaluation des connaissances';

$quiz = $DB->get_record('quiz', [
    'course' => $course->id,
    'name' => $quizname
]);

if (!$quiz) {
    $module = new stdClass();
    $module->modulename = 'quiz';
    $module->module = $DB->get_field('modules', 'id', [
        'name' => 'quiz'
    ]);
    $module->course = $course->id;
    $module->section = 3;
    $module->name = $quizname;
    $module->intro = 'Testez vos connaissances en cybersecurite.';
    $module->introformat = FORMAT_HTML;
    $module->visible = 1;
    $module->timelimit = 600;
    $module->quizpassword = '';
    $module->attempts = 2;
    $module->grade = 10;
    $module->gradepass = 6;
    $module->grademethod = 1;
    $module->preferredbehaviour = 'deferredfeedback';

    add_moduleinfo($module, $course);
}

echo "Quiz Moodle configure.\n";

// Questions et reponses du quiz.
$questions = [
    [
        'question' => 'Quels sont les trois piliers de la cybersecurite ?',
        'answers' => [
            'Confidentialite, integrite et disponibilite',
            'Authentification, rapidite et stockage',
            'Internet, reseau et serveur'
        ]
    ],
    [
        'question' => 'Quel est le but du phishing ?',
        'answers' => [
            'Voler des informations sensibles',
            'Ameliorer la securite',
            'Mettre a jour un logiciel'
        ]
    ],
    [
        'question' => 'Que signifie MFA ?',
        'answers' => [
            'Authentification multifacteur',
            'Module de filtrage automatique',
            'Methode de fichier avancee'
        ]
    ],
    [
        'question' => 'Quelle pratique ameliore la securite ?',
        'answers' => [
            'Effectuer regulierement les mises a jour',
            'Partager ses mots de passe',
            'Desactiver les protections'
        ]
    ],
    [
        'question' => 'Quel est le but d un rancongiciel ?',
        'answers' => [
            'Bloquer les donnees et exiger une rancon',
            'Accelerer un ordinateur',
            'Proteger les fichiers'
        ]
    ]
];      
// Enregistrement des questions dans le quiz.
require_once($CFG->dirroot . '/lib/questionlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

$quiz = $DB->get_record('quiz', [
    'course' => $course->id,
    'name' => $quizname
], '*', MUST_EXIST);

$cm = get_coursemodule_from_instance(
    'quiz', $quiz->id, $course->id, false, MUST_EXIST
);

$quizcontext = context_module::instance($cm->id);
$category = question_get_default_category($quizcontext->id, true);

foreach ($questions as $index => $item) {
    $name = 'CYBER465-Q' . ($index + 1);

    $questionid = $DB->get_field_sql(
        "SELECT q.id
           FROM {question} q
           JOIN {question_versions} qv ON qv.questionid = q.id
           JOIN {question_bank_entries} qbe
             ON qbe.id = qv.questionbankentryid
          WHERE qbe.questioncategoryid = :category
            AND q.name = :name
       ORDER BY qv.version DESC",
        ['category' => $category->id, 'name' => $name],
        IGNORE_MULTIPLE
    );

    if (!$questionid) {
        $question = new stdClass();
        $question->qtype = 'multichoice';

        $form = new stdClass();
        $form->category = (string)$category->id;
        $form->name = $name;
        $form->questiontext = [
            'text' => $item['question'],
            'format' => FORMAT_HTML
        ];
        $form->generalfeedback = [
            'text' => '',
            'format' => FORMAT_HTML
        ];
        $form->defaultmark = 2;
        $form->penalty = 0;
        $form->single = 1;
        $form->shuffleanswers = 1;
        $form->answernumbering = 'abc';
        $form->status = 'ready';

        foreach ($item['answers'] as $i => $answer) {
            $form->answer[$i] = [
                'text' => $answer,
                'format' => FORMAT_HTML
            ];
            $form->fraction[$i] = ($i === 0) ? 1 : 0;
            $form->feedback[$i] = [
                'text' => '',
                'format' => FORMAT_HTML
            ];
        }

        foreach (['correctfeedback',
                  'partiallycorrectfeedback',
                  'incorrectfeedback'] as $field) {
            $form->$field = [
                'text' => '',
                'format' => FORMAT_HTML
            ];
        }

        $saved = question_bank::get_qtype('multichoice')
            ->save_question($question, $form);

        $questionid = $saved->id;
    }

    quiz_add_quiz_question($questionid, $quiz, 0, 2);
}

echo "Questions du quiz configurees.\n";
// Recalcul du total des points du quiz.
\mod_quiz\quiz_settings::create($quiz->id)
    ->get_grade_calculator()
    ->recompute_quiz_sumgrades();

echo "Total des points du quiz recalcule.\n";
