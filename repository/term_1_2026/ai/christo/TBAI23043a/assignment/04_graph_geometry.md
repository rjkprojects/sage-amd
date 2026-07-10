# 🎓 **Lecture 4: Graph Theory & Geometric Modelling**
*"Connect the Dots, Build 3D Worlds, and Outsmart the System!"*

---

## 🧠 **What’s the Big Idea?**
Computers don’t just "see" pictures—they **understand shapes, spaces, and connections**. This lecture is all about:
1. **Graph Theory**: Like **connect-the-dots on steroids**—mapping roads, social networks, or even video game levels.
2. **Geometric Modelling**: Building **3D objects** (like chairs, cars, or castles) using math.

**Why’s this important?**
- **Video Games**: Designing levels, characters, and worlds.
- **Navigation**: GPS maps, self-driving cars, and delivery routes.
- **Security**: Cracking codes, solving puzzles, and outsmarting hackers.
- **Engineering**: Building bridges, robots, and skyscrapers.

---

## 🚂 **Analogy Time!**
### **Graph Theory = Spy Networks 🕵️‍♂️**
Imagine you’re a **spy** trying to deliver a secret message:
- **Dots (Nodes)**: Your **spy friends** (Alice, Bob, Charlie).
- **Lines (Edges)**: **Secret tunnels** connecting them.
- **Mission**: Find the **fastest route** from Alice to Charlie **without getting caught**!

### **Geometric Modelling = LEGO Castles 🏰**
Imagine you’re building a **LEGO castle**:
- **Dots**: The **corners** of your castle (towers, walls, doors).
- **Lines**: The **edges** connecting the corners.
- **Shapes**: The **walls, floors, and roofs** (triangles, squares, cubes).
- **Goal**: Build a **3D castle** that doesn’t fall apart!

---

## 🔍 **Key Concepts (The Magic Behind the Scenes)**
### **Graph Theory (Dots and Lines)**
Graphs are **maps of connections**. They’re made of:
- **Nodes (Vertices)**: The **dots** (people, places, or things).
- **Edges**: The **lines** connecting the dots (friendships, roads, or wires).

**Types of Graphs:**
| Type | Example | Real-World Use |
|------|---------|----------------|
| **Undirected** | Friendships (Alice ↔ Bob) | Social networks, maps |
| **Directed** | One-way streets (Alice → Bob) | GPS, web links |
| **Weighted** | Road lengths (Alice --5km--> Bob) | Delivery routes, flight paths |
| **Trees** | Family trees (Grandma → Mom → You) | File systems, decision trees |

**Fun Fact:**
Google Maps uses **graph theory** to find the **fastest route** to your destination!

---

### **Geometric Modelling (Building 3D Worlds)**
Geometric modelling is about **building 3D objects** using math. It’s like **digital LEGO**!

**Key Ideas:**
- **Vertices**: The **corners** of a shape (like the corners of a cube).
- **Edges**: The **lines** connecting the corners.
- **Faces**: The **flat surfaces** (like the sides of a cube).
- **Meshes**: A **collection of faces** that make up a 3D object.

**Example:**
A **cube** has:
- **8 vertices** (corners).
- **12 edges** (lines).
- **6 faces** (squares).

**Fun Fact:**
Pixar uses **geometric modelling** to create **3D characters** like Woody and Buzz!

---

## 🚀 **Let’s Get Hands-On!**
### **Build a Spy Network (Graph Theory)**
Let’s use **NetworkX** (a Python library for graphs) to build a **spy network** and find the **fastest route** to deliver a secret message!

```python
import networkx as nx
import matplotlib.pyplot as plt

# Create a graph (spy network)
G = nx.Graph()

# Add spies (nodes)
G.add_nodes_from(["Alice", "Bob", "Charlie", "Dave", "Eve"])

# Add secret tunnels (edges) with distances (weights)
G.add_edge("Alice", "Bob", weight=4)
G.add_edge("Alice", "Charlie", weight=2)
G.add_edge("Bob", "Charlie", weight=1)
G.add_edge("Bob", "Dave", weight=5)
G.add_edge("Charlie", "Eve", weight=3)
G.add_edge("Dave", "Eve", weight=2)

# Draw the spy network
pos = nx.spring_layout(G)  # Positions for all nodes
nx.draw(G, pos, with_labels=True, node_color="lightblue", node_size=1000)
edge_labels = nx.get_edge_attributes(G, "weight")
nx.draw_networkx_edge_labels(G, pos, edge_labels=edge_labels)
plt.title("Spy Network")
plt.show()

# Find the shortest path (fastest route)
shortest_path = nx.shortest_path(G, source="Alice", target="Eve", weight="weight")
print(f"Fastest route: {' → '.join(shortest_path)}")
```

**What’s happening?**
1. We **create a graph** (spy network).
2. We **add spies** (nodes) and **secret tunnels** (edges with distances).
3. We **draw the network** (like a map).
4. We **find the fastest route** from Alice to Eve.

**Challenge:**
Try adding **more spies** and **secret tunnels** to make the network **bigger and more complex**!

---

### **Build a 3D Cube (Geometric Modelling)**
Let’s use **Matplotlib** to build a **3D cube**—like a **digital LEGO block**!

```python
import numpy as np
import matplotlib.pyplot as plt
from mpl_toolkits.mplot3d import Axes3D

# Define the vertices (corners) of a cube
vertices = np.array([
    [0, 0, 0],  # Bottom front left
    [1, 0, 0],  # Bottom front right
    [1, 1, 0],  # Bottom back right
    [0, 1, 0],  # Bottom back left
    [0, 0, 1],  # Top front left
    [1, 0, 1],  # Top front right
    [1, 1, 1],  # Top back right
    [0, 1, 1]   # Top back left
])

# Define the edges (lines connecting the corners)
edges = [
    [0, 1], [1, 2], [2, 3], [3, 0],  # Bottom face
    [4, 5], [5, 6], [6, 7], [7, 4],  # Top face
    [0, 4], [1, 5], [2, 6], [3, 7]   # Vertical edges
]

# Define the faces (sides of the cube)
faces = [
    [0, 1, 2, 3],  # Bottom
    [4, 5, 6, 7],  # Top
    [0, 1, 5, 4],  # Front
    [2, 3, 7, 6],  # Back
    [1, 2, 6, 5],  # Right
    [0, 3, 7, 4]   # Left
]

# Create a 3D plot
fig = plt.figure()
ax = fig.add_subplot(111, projection="3d")

# Draw the edges
for edge in edges:
    ax.plot3D(*zip(vertices[edge[0]], vertices[edge[1]]), color="black")

# Draw the faces (semi-transparent)
for face in faces:
    ax.add_collection3d(
        plt.Poly3DCollection(
            [vertices[face]],
            alpha=0.5,
            linewidths=1,
            edgecolor="black",
            facecolor="lightblue"
        )
    )

# Set the limits and labels
ax.set_xlim([0, 1])
ax.set_ylim([0, 1])
ax.set_zlim([0, 1])
ax.set_xlabel("X")
ax.set_ylabel("Y")
ax.set_zlabel("Z")
ax.set_title("3D Cube")
plt.show()
```

**What’s happening?**
1. We **define the vertices** (corners) of a cube.
2. We **define the edges** (lines connecting the corners).
3. We **define the faces** (sides of the cube).
4. We **draw the cube** in 3D!

**Challenge:**
Try **rotating the cube** or **adding more shapes** (like a pyramid or a sphere)!

---

### **Solve a Maze (Graph Theory + Pathfinding)**
Let’s use **graph theory** to solve a **maze**—like a **video game hero** finding the treasure!

```python
import numpy as np
import matplotlib.pyplot as plt
from matplotlib.colors import ListedColormap

# Define the maze (0 = path, 1 = wall)
maze = np.array([
    [1, 1, 1, 1, 1, 1, 1],
    [1, 0, 0, 0, 0, 0, 1],
    [1, 0, 1, 1, 1, 0, 1],
    [1, 0, 1, 0, 0, 0, 1],
    [1, 0, 1, 0, 1, 0, 1],
    [1, 0, 0, 0, 1, 0, 1],
    [1, 1, 1, 1, 1, 1, 1]
])

# Define the start and end points
start = (1, 1)
end = (5, 5)

# Create a graph from the maze
G = nx.Graph()

# Add nodes (each cell in the maze)
for i in range(maze.shape[0]):
    for j in range(maze.shape[1]):
        if maze[i, j] == 0:  # Only add path cells (not walls)
            G.add_node((i, j))

# Add edges (connections between adjacent cells)
for i in range(maze.shape[0]):
    for j in range(maze.shape[1]):
        if maze[i, j] == 0:  # Only for path cells
            # Check right neighbor
            if j + 1 < maze.shape[1] and maze[i, j + 1] == 0:
                G.add_edge((i, j), (i, j + 1))
            # Check bottom neighbor
            if i + 1 < maze.shape[0] and maze[i + 1, j] == 0:
                G.add_edge((i, j), (i + 1, j))

# Find the shortest path
shortest_path = nx.shortest_path(G, source=start, target=end)

# Visualize the maze and the path
cmap = ListedColormap(["black", "white", "lightgreen", "red"])
maze_with_path = maze.copy()
for (i, j) in shortest_path:
    maze_with_path[i, j] = 2  # Mark the path
maze_with_path[start] = 3  # Mark the start
maze_with_path[end] = 4    # Mark the end

plt.imshow(maze_with_path, cmap=cmap)
plt.title("Maze Solver")
plt.show()
print(f"Shortest path: {shortest_path}")
```

**What’s happening?**
1. We **define a maze** (0 = path, 1 = wall).
2. We **create a graph** from the maze (nodes = path cells, edges = connections).
3. We **find the shortest path** from start to end.
4. We **visualize the maze** with the path highlighted.

**Challenge:**
Try **changing the maze** or **adding more obstacles** to make it harder!

---

## 🎯 **Challenge Time!**
### **Challenge 1: Build a Social Network**
Use **NetworkX** to:
1. Create a **social network** (nodes = people, edges = friendships).
2. Find the **most popular person** (the one with the most friends).
3. Find the **shortest path** between two people (like "How many friends connect Alice and Eve?").

**Hint:**
Use `nx.degree_centrality()` to find the most popular person.

---

### **Challenge 2: Build a 3D Pyramid**
Use **Matplotlib** to:
1. Define the **vertices** of a pyramid (4 triangular faces + 1 square base).
2. Draw the **edges** and **faces** in 3D.
3. **Rotate the pyramid** to see it from different angles.

**Hint:**
A pyramid has **5 vertices** (4 corners of the base + 1 apex).

---

### **Challenge 3: Design a Video Game Level**
Use **graph theory** to:
1. Create a **video game level** (nodes = rooms, edges = doors).
2. Add **enemies, traps, and treasures** to the rooms.
3. Find the **safest path** from the start to the treasure (avoiding enemies).

**Hint:**
Use **weighted edges** to represent danger levels.

---

## 📚 **Summary**
In this lecture, you learned:
1. **Graph Theory**: Dots and lines that map connections (like spy networks or mazes).
2. **Geometric Modelling**: Building 3D objects (like cubes or pyramids) using math.
3. **How to apply this in real life**: Solving mazes, designing video game levels, and building 3D worlds.

---

## 🚀 **What’s Next?**
In **Lecture 5**, we’ll dive into **Statistical Learning**—teaching computers to **make smart guesses** (like predicting the weather or sorting M&Ms by color).

**Ready to level up?** Let’s go! 🚀