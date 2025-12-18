const WebSocket = require('ws');

const PORT = process.env.PORT || 8080;
const wss = new WebSocket.Server({ port: PORT });
const rooms = {};

wss.on('connection', ws => {
    console.log('New client connected');

    ws.on('message', message => {
        let data;
        try {
            data = JSON.parse(message);
        } catch (e) {
            console.error('Failed to parse message:', message.toString());
            return;
        }

        const { forumId, type, username } = data;

        if (!forumId) {
            console.warn('Received message without a forumId. Ignoring.');
            return;
        }

        if (!rooms[forumId]) {
            rooms[forumId] = [];
        }

        if (type === 'join') {
            console.log(`User '${username}' attempting to join forum '${forumId}'`);

            const existingUsers = rooms[forumId].map(client => ({
                username: client.username,
                displayName: client.displayName,
                profilePicture: client.profilePicture
            }));

            if (existingUsers.length > 0) {
                 ws.send(JSON.stringify({
                    type: 'existing-users',
                    users: existingUsers,
                    forumId: forumId
                }));
            }

            if (!rooms[forumId].find(client => client.username === username)) {
                ws.forumId = forumId;
                ws.username = username;
                ws.displayName = data.displayName;
                ws.profilePicture = data.profilePicture;
                rooms[forumId].push(ws);
                console.log(`Successfully added '${username}' to forum '${forumId}'. Total users: ${rooms[forumId].length}`);

                const joinMessage = {
                    type: 'join',
                    from: username,
                    displayName: data.displayName,
                    profilePicture: data.profilePicture,
                    forumId: forumId
                };
                
                rooms[forumId].forEach(client => {
                    if (client !== ws && client.readyState === WebSocket.OPEN) {
                        client.send(JSON.stringify(joinMessage));
                    }
                });
            }

        } else if (data.to) {
            const targetClient = rooms[forumId]?.find(client => client.username === data.to);
            if (targetClient && targetClient.readyState === WebSocket.OPEN) {
                targetClient.send(JSON.stringify(data));
            } else {
                console.warn(`Could not find target '${data.to}' in forum '${forumId}' or connection is not open.`);
            }
        } else {
             rooms[forumId]?.forEach(client => {
                if (client.username !== data.from && client.readyState === WebSocket.OPEN) {
                    client.send(JSON.stringify(data));
                }
            });
        }
    });

    ws.on('close', () => {
        console.log(`Client '${ws.username}' disconnected.`);
        const { forumId, username } = ws;

        if (forumId && username && rooms[forumId]) {
            rooms[forumId] = rooms[forumId].filter(client => client.username !== username);
            console.log(`Removed '${username}' from forum '${forumId}'. Remaining users: ${rooms[forumId].length}`);

            const leaveMessage = {
                type: 'leave',
                from: username,
                forumId: forumId
            };
            rooms[forumId].forEach(client => client.send(JSON.stringify(leaveMessage)));

            if (rooms[forumId].length === 0) {
                console.log(`Forum '${forumId}' is empty. Deleting room.`);
                delete rooms[forumId];
            }
        }
    });

    ws.on('error', (err) => {
        console.error('WebSocket error:', err);
    });
});

console.log('WebSocket signaling server is running on ws://localhost:8080');
