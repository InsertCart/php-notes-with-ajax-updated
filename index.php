<?php
// Database connection
$host = 'localhost';
$user = 'root';
$password = '';
$dbname = 'note_taking_app';
$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Create table if not exists
$tableCheckQuery = "
CREATE TABLE IF NOT EXISTS notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
";
$conn->query($tableCheckQuery);

$tableCategoriesQuery = "
CREATE TABLE IF NOT EXISTS categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL
)";
$conn->query($tableCategoriesQuery);
// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];
    $noteId = $_POST['note_id'] ?? null;
    $content = $_POST['content'] ?? '';
    $categoryId = $_POST['category_id'] ?? null;

   if ($action === 'save') {
        if ($noteId) {
            // Update existing note
            $stmt = $conn->prepare("UPDATE notes SET content = ?, category_id = ? WHERE id = ?");
            $stmt->bind_param('sii', $content, $categoryId, $noteId);
        } else {
            // Create new note
            $stmt = $conn->prepare("INSERT INTO notes (content, category_id) VALUES (?, ?)");
            $stmt->bind_param('si', $content, $categoryId);
        }
        $stmt->execute();
        echo $noteId ?: $conn->insert_id;
        exit;
    }

    if ($action === 'fetch') {
        $result = $conn->query("SELECT notes.*, categories.name AS category_name FROM notes LEFT JOIN categories ON notes.category_id = categories.id ORDER BY notes.id DESC");
        $notes = [];
        while ($row = $result->fetch_assoc()) {
            $notes[] = $row;
        }
        echo json_encode($notes);
        exit;
    }

    if ($action === 'fetch_categories') {
        $result = $conn->query("SELECT * FROM categories");
        $categories = [];
        while ($row = $result->fetch_assoc()) {
            $categories[] = $row;
        }
        echo json_encode($categories);
        exit;
    }

    if ($action === 'delete') {
        if ($noteId) {
            $stmt = $conn->prepare("DELETE FROM notes WHERE id = ?");
            $stmt->bind_param('i', $noteId);
            $stmt->execute();
            echo "success";
        } else {
            echo "error";
        }
        exit;
    }

    if ($action === 'add_category') {
        $categoryName = $_POST['category_name'] ?? '';
        if ($categoryName) {
            $stmt = $conn->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->bind_param('s', $categoryName);
            $stmt->execute();
            echo $conn->insert_id;
        } else {
            echo "error";
        }
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Note Taking App</title>
    <script src="tinymce/tinymce.min.js" referrerpolicy="origin"></script>
	<link href="css/bootstrap.min.css" rel="stylesheet">
    <script>
        tinymce.init({ 
            selector: '#editor',
			plugins: 'codesample lists link image table code help wordcount',			
			toolbar:
    "codesample | code | media | undo redo | blocks | bold italic | alignleft aligncenter alignright alignjustify | outdent indent | link | image",
            entity_encoding: 'raw' // Prevents encoding of HTML entities
        });

        document.addEventListener('DOMContentLoaded', function () {
            let categories = []; // Global array to store categories
			fetchNotes();
			fetchCategories();

            document.getElementById('save-note').addEventListener('click', function () {
                const noteId = document.getElementById('note-id').value;
                const content = tinymce.get('editor').getContent(); 
				const categoryId = document.getElementById('category-select').value;
					saveNote(noteId, content, categoryId);
            });

            document.getElementById('new-note').addEventListener('click', function () {
                document.getElementById('note-id').value = '';
                tinymce.get('editor').setContent('');
				 document.getElementById('category-select').value = '';
            });
			
			document.getElementById('add-category').addEventListener('click', function () {
				const categoryName = document.getElementById('new-category').value;
				addCategory(categoryName);
			});
        });

        function fetchNotes() {
            fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'fetch' })
            })
            .then(response => response.json())
            .then(notes => {
                const container = document.getElementById('notes-container');
                container.innerHTML = '';
                notes.forEach(note => {
                    const div = document.createElement('div');
                    div.classList.add('note');
                    div.classList.add('bg-body-tertiary');
                    div.innerHTML = `<div class="category alert alert-danger"><strong>Category:</strong> ${note.category_name || 'None'}</div><div class="note-content p-3">${note.content}</div>
                        <button class="btn btn-secondary rounded-pill px-3" data-id="${note.id}" 
                        data-content="${encodeURIComponent(note.content)}" 
                        onclick="editNote(this)"> <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16"><path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/><path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/></svg> Edit</button>
						 <button class="btn btn-danger rounded-pill px-3" onclick="deleteNote(${note.id})"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-lg" viewBox="0 0 16 16"><path d="M2.146 2.854a.5.5 0 1 1 .708-.708L8 7.293l5.146-5.147a.5.5 0 0 1 .708.708L8.707 8l5.147 5.146a.5.5 0 0 1-.708.708L8 8.707l-5.146 5.147a.5.5 0 0 1-.708-.708L7.293 8z"/></svg> Delete</button>
                    `;
                    container.appendChild(div);
                });
            });
        }
		
		   function fetchCategories() {
				fetch('index.php', {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: new URLSearchParams({ action: 'fetch_categories' })
				})
				.then(response => response.json())
				.then(data => {
					categories = data; // Store categories globally
					const select = document.getElementById('category-select');
					select.innerHTML = '<option value="">Select Category</option>';
					categories.forEach(category => {
						const option = document.createElement('option');
						option.value = category.id;
						option.textContent = category.name;
						select.appendChild(option);
					});
				});
			}

        function saveNote(noteId, content, categoryId) {
            fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'save', note_id: noteId, content, category_id: categoryId })
            })
            .then(response => response.text())
            .then(() => {
                fetchNotes();
                document.getElementById('new-note').click();
            });
        }
		 function deleteNote(noteId) {
            if (confirm('Are you sure you want to delete this note?')) {
                fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ action: 'delete', note_id: noteId })
                })
                .then(response => response.text())
                .then(result => {
                    if (result === 'success') {
                        fetchNotes();
                    } else {
                        alert('Error deleting note');
                    }
                });
            }
        }
		
		

       let activeEditor = null; // Track the active editor instance

			function editNote(button) {
				const noteId = button.getAttribute('data-id');
				const content = decodeURIComponent(button.getAttribute('data-content'));

				// Destroy the previous editor if one exists
				if (activeEditor) {
					tinymce.remove(activeEditor);
					activeEditor = null;
				}

				// Replace note content with a TinyMCE editor
				const noteDiv = button.closest('.note');
				const noteContentDiv = noteDiv.querySelector('.note-content');

				noteContentDiv.innerHTML = `<textarea id="editor-${noteId}">${content}</textarea>`;
				tinymce.init({
					selector: `#editor-${noteId}`,
					plugins: 'codesample lists link image table code help wordcount',
					toolbar:
						"codesample | code | media | undo redo | blocks | bold italic | alignleft aligncenter alignright alignjustify | outdent indent | link | image",
					setup: (editor) => {
						activeEditor = editor;
					}
				});

				// Add Save/Cancel buttons
				const actionDiv = document.createElement('div');
				actionDiv.innerHTML = `
					<button class="btn btn-primary rounded-pill px-3" onclick="saveEditedNote(${noteId})">Save</button>
					<button class="btn btn-secondary rounded-pill px-3" onclick="cancelEdit(${noteId}, '${encodeURIComponent(content)}')">Cancel</button>
				`;
				noteDiv.appendChild(actionDiv);
			}
function saveEditedNote(noteId) {
    const editor = tinymce.get(`editor-${noteId}`);
    const updatedContent = editor.getContent();

    fetch('index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'save', note_id: noteId, content: updatedContent })
    })
    .then(() => {
        fetchNotes(); // Refresh notes
    });
}
function cancelEdit(noteId, originalContent) {
    const noteDiv = document.querySelector(`#editor-${noteId}`).closest('.note');
    const noteContentDiv = noteDiv.querySelector('.note-content');

    // Restore the original content
    noteContentDiv.innerHTML = decodeURIComponent(originalContent);

    // Remove TinyMCE instance if active
    if (activeEditor) {
        tinymce.remove(activeEditor);
        activeEditor = null;
    }

    // Remove Save/Cancel buttons
    const actionDiv = noteDiv.querySelector('div:last-child');
    actionDiv.remove();
}
function addCategory(categoryName) {
            fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'add_category', category_name: categoryName })
            })
            .then(() => {
                fetchCategories();
                document.getElementById('new-category').value = '';
            });
        }

    </script>
    <style>
        .note { margin-bottom: 10px; padding: 10px; border: 1px solid #ccc; }
        .note-content { margin-bottom: 5px; }
		button svg {padding-bottom: 2px;}
		code {
    padding: 10px;
    color: #fff !important;
    display: block;
    background-color: #212529;
    border-radius: .2rem;
}
.tox .tox-promotion {
    visibility: hidden;
}
.category.alert.alert-danger {
    display: inline-block;
    padding: 10px;
}
    </style>
</head>
<body>
<div class="container my-5">
    <h1>Note Taking App</h1>
    <textarea id="editor"></textarea>
    <input type="hidden" id="note-id">
	<select id="category-select" class="form-select my-2"></select>
	</br>
    <button class="btn btn-primary rounded-pill px-3" id="save-note"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-floppy2" viewBox="0 0 16 16"><path d="M1.5 0h11.586a1.5 1.5 0 0 1 1.06.44l1.415 1.414A1.5 1.5 0 0 1 16 2.914V14.5a1.5 1.5 0 0 1-1.5 1.5h-13A1.5 1.5 0 0 1 0 14.5v-13A1.5 1.5 0 0 1 1.5 0M1 1.5v13a.5.5 0 0 0 .5.5H2v-4.5A1.5 1.5 0 0 1 3.5 9h9a1.5 1.5 0 0 1 1.5 1.5V15h.5a.5.5 0 0 0 .5-.5V2.914a.5.5 0 0 0-.146-.353l-1.415-1.415A.5.5 0 0 0 13.086 1H13v3.5A1.5 1.5 0 0 1 11.5 6h-7A1.5 1.5 0 0 1 3 4.5V1H1.5a.5.5 0 0 0-.5.5m9.5-.5a.5.5 0 0 0-.5.5v3a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-3a.5.5 0 0 0-.5-.5z"/></svg> Save Note</button>
    <button class="btn btn-dark rounded-pill px-3" id="new-note"><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-plus-fill" viewBox="0 0 16 16"><path d="M12 0H4a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2M8.5 6v1.5H10a.5.5 0 0 1 0 1H8.5V10a.5.5 0 0 1-1 0V8.5H6a.5.5 0 0 1 0-1h1.5V6a.5.5 0 0 1 1 0"/></svg> New Note</button>
</br>

<div class="mt-4">
        <input type="text" id="new-category" placeholder="New Category" class="form-control">
        <button class="btn btn-success mt-2" id="add-category">Add Category</button>
		
    </div>
    <h2>Your Notes</h2>
    <div id="notes-container"></div>
	
	Your Company &copy; <?php echo date("Y"); ?> <a href="https://www.insertcart.com/build-a-simple-php-note-taking-app-with-ajax/">InsertCart Notes</a>
	</div>
</body>
</html>
