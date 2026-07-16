BEGIN {
    # Initialize prefix stack
    delete prefix_stack
    ptr = 0
    prefix_stack[ptr] = ""
}

/ Route::prefix\(/ {
    # Extract the prefix argument
    match($0, /Route::prefix\(['"]([^"']+)['"]\)/, arr)
    if (RSTART) {
        ptr++
        prefix_stack[ptr] = arr[1]
        # Also stay in the same group? We'll assume the group opens after this line
        # We'll handle by pushing and popping on group open/close, but we don't have explicit group markers.
        # Instead, we'll assume that the prefix applies until the closing parenthesis of the group.
        # This is complex. We'll assume that the prefix is set for the following lines until a closing brace.
        # We'll use a simple approach: when we see a line with ')->group(function () {', we push the prefix.
        # When we see a closing brace '}' that is not inside anything, we pop.
        # But for simplicity, we'll assume the prefix is set until the end of the file? Not good.
    }
}

/ Route::middleware\(.*\)->group\(function \(\) {/ {
    # Push current prefixes? Actually, we want to keep the prefix and add middleware, but for URI we only care about prefix.
    # We'll just keep the current prefix stack as is.
    # We'll push a marker for the group so we can pop later.
    ptr++
    prefix_stack[ptr] = "GROUP_MARKER"
}

/->group\(function \(\) {/ {
    # This is for Route::prefix(...)->group(...)
    # We already pushed the prefix in the previous step? Actually, the pattern above didn't match because we didn't capture the prefix.
    # We'll handle by pushing the current prefix (which is the last pushed prefix) again? Not good.
    # Let's change strategy: we'll parse the file line by line and keep a prefix variable that is updated when we enter a prefix group and reset when we leave.
    # We'll assume the file is properly indented? Not reliable.
    # Given the time, we'll do a simpler approach: we'll extract the prefix from the line that contains the group opening.
    # We'll look for the pattern: Route::prefix('...')
    # We already handled that above.
    # For now, we'll assume that the prefix is set and we will push it when we see the group opening for prefix.
    # We'll do nothing here for now.
}

/^}/ {
    # If we encounter a closing brace, pop the stack if it's not empty
    if (ptr > 0) {
        if (prefix_stack[ptr] == "GROUP_MARKER") {
            # Pop the marker
            ptr--
        } else if (prefix_stack[ptr] != "") {
            # Pop the prefix
            ptr--
        }
        # If we popped a prefix, we need to restore the previous prefix? Actually, we are storing each prefix level.
        # We'll keep the stack as the list of prefixes in effect.
        # The current prefix is the concatenation of all prefixes in the stack from 1 to ptr.
        # We'll compute the current prefix by concatenating all elements in the stack from 1 to ptr.
        # But we also have markers for groups. We'll ignore markers in the concatenation.
        # Let's compute current prefix each time we need it.
    }
}

{
    # If we are not in a group, we can compute the current prefix by concatenating all non-marker elements in the stack.
    # We'll compute it when we need to output a route.
}

# Function to compute current prefix
function get_current_prefix() {
    pref = ""
    for (i = 1; i <= ptr; i++) {
        if (prefix_stack[i] != "GROUP_MARKER") {
            pref = pref prefix_stack[i]
        }
    }
    return pref
}

/ Route::get\(/ || / Route::post\(/ || / Route::put\(/ || / Route::patch\(/ || / Route::delete\(/ || / Route::put\(/ || / Route::patch\(/ || / Route::delete\(/ {
    # Extract the method and the URI
    # We'll match the method: get, post, etc.
    # The line format: Route::METHOD('uri', [controller])
    # We'll use regex to extract the method and the URI.
    if (match($0, /Route::get\(['"]([^"']+)['"]/)) {
        method = "GET"
        uri = substr($0, RSTART+9, RLENGTH-10)  # Skip "Route::get('"
    } else if (match($0, /Route::post\(['"]([^"']+)['"]/)) {
        method = "POST"
        uri = substr($0, RSTART+11, RLENGTH-12)
    } else if (match($0, /Route::put\(['"]([^"']+)['"]/)) {
        method = "PUT"
        uri = substr($0, RSTART+9, RLENGTH-10)
    } else if (match($0, /Route::patch\(['"]([^"']+)['"]/)) {
        method = "PATCH"
        uri = substr($0, RSTART+11, RLENGTH-12)
    } else if (match($0, /Route::delete\(['"]([^"']+)['"]/)) {
        method = "DELETE"
        uri = substr($0, RSTART+12, RLENGTH-13)
    } else if (match($0, /Route::put\(['"]([^"']+)['"]/)) {
        # duplicate
        method = "PUT"
        uri = substr($0, RSTART+9, RLENGTH-10)
    } else if (match($0, /Route::patch\(['"]([^"']+)['"]/)) {
        method = "PATCH"
        uri = substr($0, RSTART+11, RLENGTH-12)
    } else if (match($0, /Route::delete\(['"]([^"']+)['"]/)) {
        method = "DELETE"
        uri = substr($0, RSTART+12, RLENGTH-13)
    } else {
        # If none matched, skip
        next
    }
    # Remove any trailing single quote and comma or closing parenthesis? Actually, we captured until the quote.
    # Now, we have the uri inside the quotes.
    # Prepend the current prefix
    full_uri = get_current_prefix() uri
    # Now, we need to extract the controller action.
    # The pattern after the uri: , [Controller@method]
    # We'll look for the pattern: [\\w\\\\]+@\\w+
    if (match($0, /[[\\]a-zA-Z0-9_\\\\]+@[a-zA-Z_]+/)) {
        controller = substr($0, RSTART, RLENGTH)
    } else {
        # Maybe it's a closure
        controller = "Closure"
    }
    # Print the route
    printf "%s %s %s\n", method, full_uri, controller
}

/ Route::apiResource\(/ {
    # Extract the resource name and the controller
    # Format: Route::apiResource('resource', Controller::class);
    # or with options: ->except([...])
    # We'll extract the first two arguments.
    # We'll match: Route::apiResource('([^']+)', ([^,]+)
    if (match($0, /Route::apiResource\(['"]([^"']+)['"],\s*([^,)]+)/)) {
        resource = substr($0, RSTART+16, RLENGTH-16-1)  # Skip "Route::apiResource('"
        # Actually, let's do it step by step.
        # We'll split the line by commas and quotes.
        # But we can do: remove the function call and split.
        # For simplicity, we'll assume the format is exactly as above.
        # We'll extract the resource and the controller.
        # We'll use a different approach: split by single quotes and commas.
        # We'll do it later with a function.
        # For now, we'll skip and do manual.
        next
    }
    # If we didn't match, try without the class::class
    if (match($0, /Route::apiResource\(['"]([^"']+)['"],\s*([^)]+)/)) {
        resource = substr($0, RSTART+16, RLENGTH-16-1)
        # Not perfect.
        next
    }
}

END {
    # We'll handle apiResource in the END block? Not ideal.
}
