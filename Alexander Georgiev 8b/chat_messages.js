document.addEventListener('DOMContentLoaded', function() {
            const chatMessages = document.getElementById('chat-messages');
            if (chatMessages) {
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }
        });
        
        // Modal functionality
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function openNewChatModal() {
            openModal('newChatModal');
            document.getElementById('chat_title').focus();
        }
        
        function openRenameChatModal(chatId, currentTitle) {
            document.getElementById('rename_chat_id').value = chatId;
            document.getElementById('new_title').value = currentTitle;
            openModal('renameChatModal');
            document.getElementById('new_title').focus();
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        };