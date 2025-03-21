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

// Create tables if not exist
$tableNotesQuery = "
CREATE TABLE IF NOT EXISTS notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    content TEXT NOT NULL,
    category_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conn->query($tableNotesQuery);

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
    <title>Note Taking App with Categories</title>
    <script src="tinymce/tinymce.min.js" referrerpolicy="origin"></script>
    <link href="css/bootstrap.min.css" rel="stylesheet">
    <script>
        tinymce.init({ 
            selector: '#editor',
            plugins: 'codesample lists link image table code help wordcount',
		   
            toolbar: "codesample | code | media | undo redo | blocks | bold italic | alignleft aligncenter alignright alignjustify | outdent indent | link | image",
            entity_encoding: 'raw' 
        });

        document.addEventListener('DOMContentLoaded', function () {
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
                    div.innerHTML = `<div class="note-content p-3">${note.content}</div>
                        <div><strong>Category:</strong> ${note.category_name || 'None'}</div>
                        <button class="btn btn-secondary rounded-pill px-3" data-id="${note.id}" 
                        data-content="${encodeURIComponent(note.content)}" 
                        data-category="${note.category_id}" 
                        onclick="editNote(this)"> Edit</button>
                        <button class="btn btn-danger rounded-pill px-3" onclick="deleteNote(${note.id})"> Delete</button>`;
					  
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
            .then(categories => {
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
											  
            .then(() => {
                fetchNotes();
                document.getElementById('new-note').click();
            });
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
								   
		
				  
						   
				   
							  
						 
 
					 
					   
 
    </style>
</head>
<body>
<div class="container my-5">
    <h1>Note Taking App with Categories</h1>
    <textarea id="editor"></textarea>
    <input type="hidden" id="note-id">
    <select id="category-select" class="form-select my-2"></select>
    <button class="btn btn-primary rounded-pill px-3" id="save-note"> Save Note</button>
    <button class="btn btn-dark rounded-pill px-3" id="new-note"> New Note</button>

    <div class="mt-4">
        <input type="text" id="new-category" placeholder="New Category" class="form-control">
        <button class="btn btn-success mt-2" id="add-category">Add Category</button>
    </div>

    <h2 class="mt-5">Your Notes</h2>
    <div id="notes-container"></div>
 
																																																	  
</div>
</body>
</html>
