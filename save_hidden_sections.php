<?php
session_start();
$module = $_POST["module"] ?? "";
$type   = $_POST["type"] ?? "";
$hidden = $_POST["hidden"] ?? "1";
$allowed = ["pos", "kitchen", "furniture", "ac"];
if (!in_array($module, $allowed, true) || !$type) exit;

if ($hidden === "1") {
  // User toggled OFF — add to hidden_sections, remove from shown_sections
  if (!isset($_SESSION["wizard"]["hidden_sections"][$module])) {
    $_SESSION["wizard"]["hidden_sections"][$module] = [];
  }
  if (!in_array($type, $_SESSION["wizard"]["hidden_sections"][$module], true)) {
    $_SESSION["wizard"]["hidden_sections"][$module][] = $type;
  }
  // Remove from shown_sections
  if (isset($_SESSION["wizard"]["shown_sections"][$module])) {
    $_SESSION["wizard"]["shown_sections"][$module] = array_values(
      array_filter($_SESSION["wizard"]["shown_sections"][$module], fn($t) => $t !== $type)
    );
  }
} else {
  // User toggled ON — remove from hidden_sections, add to shown_sections
  if (isset($_SESSION["wizard"]["hidden_sections"][$module])) {
    $_SESSION["wizard"]["hidden_sections"][$module] = array_values(
      array_filter($_SESSION["wizard"]["hidden_sections"][$module], fn($t) => $t !== $type)
    );
  }
  if (!isset($_SESSION["wizard"]["shown_sections"][$module])) {
    $_SESSION["wizard"]["shown_sections"][$module] = [];
  }
  if (!in_array($type, $_SESSION["wizard"]["shown_sections"][$module], true)) {
    $_SESSION["wizard"]["shown_sections"][$module][] = $type;
  }
}
echo json_encode(["ok" => true]);
