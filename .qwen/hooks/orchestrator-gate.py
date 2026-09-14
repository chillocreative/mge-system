#!/usr/bin/env python3
"""PreToolUse gate for the MGE-PMS orchestrator workflow (docs/ORCHESTRATOR.md).

Two rules, enforced on the main session only (the orchestrator). Sub-agents are
identified by the `agent_type` field that Qwen Code adds to hook input, and are
not touched by this script:

  1. The orchestrator may only write under `.qwen/**` and `docs/**`. Everything
     else in the tree requires a work order for the writer.
  2. `git commit` is refused unless a written review signs off on the exact state
     of the worktree: an APPROVE review must exist, must not pre-date the writer's
     run report, and nothing the review could not have seen may be newer than it.

Exit 0 with no stdout leaves the normal approval flow untouched. A deny is
emitted as JSON on stdout with exit 0, per the PreToolUse contract.

Emergency bypass: `touch .qwen/hooks/GATE_OFF` (state the reason in the review).
"""

import glob
import json
import os
import re
import subprocess
import sys

WRITE_TOOLS = {"edit", "write_file", "notebook_edit"}
OWNER_DIRS = (".qwen", "docs")
MTIME_SLACK = 2.0  # seconds; filesystem timestamps and review writing race


def deny(reason):
    print(
        json.dumps(
            {
                "hookSpecificOutput": {
                    "hookEventName": "PreToolUse",
                    "permissionDecision": "deny",
                    "permissionDecisionReason": reason,
                }
            }
        )
    )
    sys.exit(0)


def project_root(payload):
    root = (
        os.environ.get("QWEN_PROJECT_DIR")
        or payload.get("cwd")
        or os.getcwd()
    )
    return os.path.realpath(os.path.expanduser(root))


def owned_rel(target, root):
    """Relative path of target if it lives in the project, else None."""
    if not target:
        return None
    abs_target = os.path.realpath(
        target if os.path.isabs(target) else os.path.join(root, target)
    )
    try:
        rel = os.path.relpath(abs_target, root)
    except ValueError:
        return None
    if rel.startswith(".."):
        return None
    return rel


def is_owner_area(rel):
    return rel is not None and (rel.split(os.sep)[0] in OWNER_DIRS or rel == ".")


def external_allowed(target):
    """True for out-of-repo paths that belong to the orchestrator's own harness.

    Anything outside the project is not application code, but it is also not
    automatically safe to write. These areas are legitimate orchestrator state
    and are whitelisted by shape, not by convenience:

      ~/.qwen/agents/**                 user-level agent definitions
      ~/.qwen/memories/**               user (cross-project) auto-memory
      ~/.qwen/projects/*/memory/**      project auto-memory

    An earlier revision denied them as "application code", which blocked memory
    updates the runtime instructs the agent to perform (observed 2026-09-15;
    `memories/` added the same day with explicit user approval — the first fix
    covered only the project-scoped store, so cross-project memory stayed
    blocked). Everything else outside the repo is still refused, including
    ~/.qwen/settings.json, which holds provider and permission config.
    """
    if not target:
        return False
    abs_target = os.path.realpath(
        target if os.path.isabs(target) else os.path.join(os.getcwd(), target)
    )
    home = os.path.realpath(os.path.expanduser("~"))
    qwen = os.path.join(home, ".qwen")
    try:
        rel = os.path.relpath(abs_target, qwen)
    except ValueError:
        return False
    if rel.startswith(".."):
        return False
    parts = rel.split(os.sep)
    if parts[0] in ("agents", "memories"):
        return True
    if parts[0] == "projects" and "memory" in parts:
        return True
    return False


def newest(paths):
    existing = [p for p in paths if os.path.exists(p)]
    return max(existing, key=os.path.getmtime) if existing else None


def git_status(root):
    try:
        out = subprocess.run(
            ["git", "status", "--porcelain", "--untracked-files=all"],
            cwd=root,
            capture_output=True,
            text=True,
            timeout=10,
        )
    except Exception:
        return []
    paths = []
    for line in out.stdout.splitlines():
        if len(line) < 4:
            continue
        path = line[3:].split(" -> ")[-1].strip().strip('"')
        if path:
            paths.append(path)
    return paths


def check_commit(root):
    """An APPROVE review must post-date every piece of work it certifies.

    Ordering by "newest review file" was rejected: re-saving an old review, or
    a second review existing for an unrelated SPEC, would silently move the
    authority. Instead: any APPROVE review qualifies, and its timestamp must be
    newer than the writer's report and newer than every changed file outside the
    orchestrator's own areas.
    """
    reviews = glob.glob(os.path.join(root, ".qwen", "reviews", "REVIEW-*.md"))
    approved = []
    for path in reviews:
        try:
            with open(path, encoding="utf-8", errors="replace") as handle:
                if re.search(r"Verdict:\s*APPROVE", handle.read(65536), re.IGNORECASE):
                    approved.append((os.path.getmtime(path), path))
        except OSError:
            continue

    if not approved:
        deny(
            "Orchestrator gate: no approved review found in .qwen/reviews/. "
            "Nothing is committable until REVIEW-<NNN>-<slug>.md exists with "
            "'### Verdict: APPROVE' (see docs/ORCHESTRATOR.md)."
        )

    auth_mtime, auth_path = max(approved)
    auth_name = os.path.basename(auth_path)

    runs_root = os.path.join(root, ".qwen", "runs")
    newest_run = newest(
        glob.glob(os.path.join(runs_root, "RUN-*.md"))
        + glob.glob(os.path.join(runs_root, "**", "RUN-*.md"), recursive=True)
    )
    if newest_run and os.path.getmtime(newest_run) > auth_mtime + MTIME_SLACK:
        deny(
            "Orchestrator gate: "
            f"{os.path.basename(newest_run)} is newer than {auth_name} - that "
            "review cannot certify work that finished after it. Write the review "
            "last, then commit."
        )

    for rel in git_status(root):
        if is_owner_area(owned_rel(rel, root)):
            continue  # specs, reviews and this script are the orchestrator's own
        abs_path = os.path.join(root, rel)
        if not os.path.exists(abs_path):
            continue  # a deletion: the review sees it listed in the run report
        if os.path.getmtime(abs_path) > auth_mtime + MTIME_SLACK:
            deny(
                f"Orchestrator gate: {rel} was modified after {auth_name} was "
                "signed off. The commit must match the reviewed state - re-review "
                "(re-save the APPROVE review), or revert the change."
            )

    sys.exit(0)  # reviewed and clean: hand back to the normal approval flow


def main():
    try:
        payload = json.load(sys.stdin)
    except Exception:
        sys.exit(0)  # unreadable input must not wedge the session

    root = project_root(payload)
    if os.path.exists(os.path.join(root, ".qwen", "hooks", "GATE_OFF")):
        sys.exit(0)

    # Sub-agents (the writer) are outside this gate.
    if payload.get("agent_type") or payload.get("agent_id"):
        sys.exit(0)

    tool = payload.get("tool_name") or ""
    tool_input = payload.get("tool_input") or {}
    if not isinstance(tool_input, dict):
        tool_input = {}

    if tool in WRITE_TOOLS:
        target = tool_input.get("file_path") or tool_input.get("notebook_path")
        rel = owned_rel(target, root)
        if rel is None:
            if external_allowed(target):
                sys.exit(0)
            deny(
                "Orchestrator gate: '{0}' is outside this project and is not a "
                "workflow area (~/.qwen/agents/** or ~/.qwen/projects/*/memory/**). "
                "Refusing to write outside the repository on the orchestrator's "
                "own authority.".format(target or "(no path)")
            )
        if not is_owner_area(rel):
            deny(
                "Orchestrator gate: the orchestrator may only write under "
                f".qwen/** and docs/**. '{target or '(no path)'}' is application "
                "code - put it in a SPEC and delegate it to the writer "
                "sub-agent (docs/ORCHESTRATOR.md)."
            )
        sys.exit(0)

    if tool == "run_shell_command":
        command = tool_input.get("command") or ""
        if re.search(r"(?:^|[\s;&|`(])git\s+(?:-{1,2}\S*\s+)*commit\b", command):
            check_commit(root)


if __name__ == "__main__":
    main()
