<?php


require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/annotate_answer_form.php');


// Load up the necessary objects from the URL parameters
$sid = optional_param('sid', 0, PARAM_INT);
$new_save = optional_param('newSave', 0, PARAM_INT);
if ($new_save) $annotate_message = "Responses have been saved!";

$sample = $DB->get_record('annotate_sample', array('id' => $sid));
$this_sample = $sample;
if (!$sample) {
  print_error('That sample does not exist');
}
$annotate = $DB->get_record('annotate', array('id' => $sample->aid));
$course = $DB->get_record('course',array('id' => $annotate->course));
$cm = get_coursemodule_from_instance('annotate', $annotate->id, $course->id, false, MUST_EXIST);

if (!$annotate || !$course || !$cm) {
  print_error('Could not find annotate, course, or cm objects!');
}

$context = get_context_instance(CONTEXT_MODULE, $cm->id);

require_login($course, true, $cm);
$PAGE->set_url('/mod/annotate/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($annotate->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);
$PAGE->requires->css('/mod/annotate/css/annotate.css');
$PAGE->requires->css('/mod/annotate/css/jquery-ui.css');
$PAGE->requires->js('/mod/annotate/scripts/jquery.min.js');
$PAGE->requires->js('/mod/annotate/scripts/jquery-ui.min.js');
$PAGE->requires->js('/mod/annotate/scripts/annotate-image-preview.js');
annotate_set_display_type($annotate);

add_to_log($course->id, 'sort', 'view', "sample.php?sid=$sample->id", $sample->name, $cm->id);

// Get all samples for this annotate.
$samples = $DB->get_records('annotate_sample', array('aid' => $annotate->id),'name');

// Get all questions for this annotate.
$questions = $DB->get_records('annotate_question', array('aid' => $annotate->id), 'weight');

if (!$questions) {
  print_error('This activity has no prompts!  They need to be created first.');
}

$qids = array_keys($questions);

// Get all responses.
$answers = $DB->get_records_select('annotate_answer', "uid = $USER->id AND sid = $sample->id AND qid IN (" . implode(",",$qids) . ") ");

// Get all the files assocated with the samples.
$files = $DB->get_records_select('files', "filesize <> 0 AND component = 'mod_annotate' AND contextid = '$context->id' AND filearea= 'sample' AND itemid = $sid");

// Get the urls for each of the samples.
foreach ($files as $file) {
  $image_url = annotate_get_image_file_url($file);
}

// Load the form for annotating a sample.
$mform = new annotate_answer_form("sample.php?sid=$sid", array('questions' => $questions));

// Handle submitted form data.
if ($responses = $mform->get_data()) {
  $DB->delete_records_select('annotate_answer',"qid IN (" . implode(",",$qids) . ") AND uid = $USER->id AND sid = $sample->id");
  
  foreach ($responses as $key => $response) {
    $exploded_key = explode("-",$key);
    
    if (sizeof($exploded_key) > 2) { // MC
      list($junk, $qid, $response) = $exploded_key;
    }
    else if (sizeof($exploded_key) == 2 ) { // Open response
      list($junk,$qid) = $exploded_key;
    } else $qid = 0;
    
    if ($qid) {
      $new_answer = new stdClass();
      $new_answer->qid = $qid;
      $new_answer->answer = $response;
      $new_answer->uid = $USER->id;
      $new_answer->sid = $sample->id;

      $DB->insert_record('annotate_answer',$new_answer);
    }
  }
  
 redirect("sample.php?sid=$sid&newSave=1");
}



// set existing data.
$form_data = array();

foreach ($answers as $answer) {
  
  if ($questions[$answer->qid]->type == "M") { // MC Answer
    $form_data['answer-' . $answer->qid . "-" . $answer->answer] = "1";
  }
  else if ($questions[$answer->qid]->type == "O")  { // Open response
    $form_data['answer-' . $answer->qid] = $answer->answer;
  }
}
$mform->set_data($form_data);


echo $OUTPUT->header();
echo "<div class='annotate'>";
echo "<div class='annotate-message'><h3>";
echo (isset($annotate_message)) ? $annotate_message : "";
echo "</h3></div>";
echo "<div class='annotate-sample-pager responses'>";
echo "<h4>Select a " . get_string('samplename','annotate') . " to annotate</h4>";
echo "<ul>";
  foreach ($samples as $sample) {
    $class = ($sample->id == $sid) ? " class='annotate-sample-current' " : "";
    echo "<li$class><a href='sample.php?sid=$sample->id'>$sample->name</a></li>";
  }
echo "</ul></div>";

echo "<div class='annotate-wrapper'>";
echo "<div class='annotate-sample-image'>";
echo "<h4>" . get_string('samplename_caps','annotate') . " " . $this_sample->name . "</h4>";
echo "<div class='annotate-sample-image-wrapper'><img src='$image_url' alt='sample student work' />";
echo '<a href="' . $image_url . '" title="View larger image" class="ui-icon ui-icon-magnifying">View larger</a></div>';
echo "</div>";
echo "<div class='annotate-questions'>";
$mform->display();
echo "</div>";
echo "<div class='annotate-action-links'>";
echo "<span class='annotate-action-link'><a href='sampleanswers.php?sid=$sid'>Participant responses</a></span>";
echo "<span class='annotate-action-link'><a href='view.php?a=$annotate->id'>Annotate activity index</a></span>";
echo "</div>";
echo "</div>";

echo "</div>";


annotate_add_attribution_line();
echo $OUTPUT->footer();